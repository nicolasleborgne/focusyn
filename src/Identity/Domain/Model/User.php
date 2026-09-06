<?php

declare(strict_types=1);

namespace App\Identity\Domain\Model;

use App\Identity\Domain\Event\UserWasRegistered;
use App\Identity\Domain\Exception\OAuthProviderAlreadyLinked;
use App\Identity\Domain\Exception\OAuthProviderNotLinked;
use App\Identity\Domain\Exception\TwoFactorAlreadyEnabled;
use App\Identity\Domain\Exception\TwoFactorNotEnabled;
use App\Shared\Domain\AggregateRoot;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use InvalidArgumentException;

/**
 * Un compte Focusyn.
 *
 * L'agrégat ne connaît ni Doctrine ni Symfony : l'adaptateur de sécurité et le
 * mapping XML vivent dans l'infrastructure du contexte.
 */
final class User extends AggregateRoot
{
    private ?EmailAddress $pendingEmail = null;
    private ?TotpSecret $totpSecret = null;

    /**
     * Empreintes des codes de secours, jamais les codes eux-mêmes : ils sont
     * montrés une fois à l'enrôlement puis oubliés du serveur, exactement comme
     * un mot de passe.
     *
     * @var list<string>
     */
    private array $backupCodes = [];

    /** @var Collection<int, OAuthIdentity> */
    private Collection $oauthIdentities;

    private DisplayPreferences $display;

    private function __construct(
        private readonly UserId $id,
        private EmailAddress $email,
        private HashedPassword $password,
        private readonly DateTimeImmutable $registeredAt,
    ) {
        $this->oauthIdentities = new ArrayCollection();
        $this->display = DisplayPreferences::default();
    }

    public static function register(
        UserId $id,
        EmailAddress $email,
        HashedPassword $password,
        DateTimeImmutable $registeredAt,
    ): self {
        $user = new self($id, $email, $password, $registeredAt);
        $user->recordThat(new UserWasRegistered($id->toString(), $email->toString(), $registeredAt));

        return $user;
    }

    public function id(): UserId
    {
        return $this->id;
    }

    public function email(): EmailAddress
    {
        return $this->email;
    }

    public function password(): HashedPassword
    {
        return $this->password;
    }

    public function registeredAt(): DateTimeImmutable
    {
        return $this->registeredAt;
    }

    public function changePassword(HashedPassword $password): void
    {
        $this->password = $password;
    }

    public function changeEmail(EmailAddress $email): void
    {
        if ($this->email->equals($email)) {
            return;
        }

        $this->email = $email;
        $this->pendingEmail = null;
    }

    /**
     * L'adresse qui attend d'être confirmée, s'il y en a une.
     *
     * Une adresse de connexion ne se change pas sur parole : une faute de
     * frappe fermerait le compte à son propriétaire. Elle attend donc d'être
     * confirmée depuis la boîte aux lettres correspondante.
     */
    public function pendingEmail(): ?EmailAddress
    {
        return $this->pendingEmail;
    }

    public function requestEmailChange(EmailAddress $email): void
    {
        if ($this->email->equals($email)) {
            throw new InvalidArgumentException('C\'est déjà votre adresse.');
        }

        // Se raviser remplace la demande précédente : c'est la dernière qui
        // vaut, et le lien émis pour l'ancienne cesse d'ouvrir quoi que ce soit.
        $this->pendingEmail = $email;
    }

    public function confirmEmailChange(EmailAddress $email): void
    {
        if (null === $this->pendingEmail || !$this->pendingEmail->equals($email)) {
            throw new InvalidArgumentException('Aucun changement d\'adresse n\'attend cette confirmation.');
        }

        $this->email = $email;
        $this->pendingEmail = null;
    }

    public function cancelEmailChange(): void
    {
        $this->pendingEmail = null;
    }

    // ---- affichage ---------------------------------------------------------

    public function display(): DisplayPreferences
    {
        return $this->display;
    }

    public function adjustDisplay(DisplayPreferences $display): void
    {
        $this->display = $display;
    }

    // ---- double authentification -----------------------------------------

    public function hasTwoFactorEnabled(): bool
    {
        return null !== $this->totpSecret;
    }

    public function totpSecret(): ?TotpSecret
    {
        return $this->totpSecret;
    }

    /** @return list<string> */
    public function backupCodes(): array
    {
        return $this->backupCodes;
    }

    /**
     * @param list<string> $backupCodeHashes
     */
    public function enableTwoFactor(TotpSecret $secret, array $backupCodeHashes): void
    {
        if ($this->hasTwoFactorEnabled()) {
            // Remplacer un secret en silence ferait perdre l'accès à qui a déjà
            // enrôlé une application : il faut désactiver d'abord, sciemment.
            throw TwoFactorAlreadyEnabled::create();
        }

        $this->totpSecret = $secret;
        $this->backupCodes = array_values($backupCodeHashes);
    }

    public function disableTwoFactor(): void
    {
        if (!$this->hasTwoFactorEnabled()) {
            throw TwoFactorNotEnabled::create();
        }

        $this->totpSecret = null;
        $this->backupCodes = [];
    }

    public function revokeBackupCode(string $hash): void
    {
        $this->backupCodes = array_values(array_filter(
            $this->backupCodes,
            static fn (string $candidate): bool => $candidate !== $hash,
        ));
    }

    /**
     * @param list<string> $backupCodeHashes
     */
    public function replaceBackupCodes(array $backupCodeHashes): void
    {
        if (!$this->hasTwoFactorEnabled()) {
            throw TwoFactorNotEnabled::create();
        }

        $this->backupCodes = array_values($backupCodeHashes);
    }

    // ---- fournisseurs externes -------------------------------------------

    /** @return list<OAuthIdentity> */
    public function oauthIdentities(): array
    {
        return array_values($this->oauthIdentities->toArray());
    }

    public function hasOAuthIdentity(OAuthProvider $provider): bool
    {
        return null !== $this->identityFor($provider);
    }

    public function linkOAuthIdentity(
        OAuthIdentityId $id,
        OAuthProvider $provider,
        string $externalId,
        DateTimeImmutable $linkedAt,
    ): void {
        if ($this->hasOAuthIdentity($provider)) {
            throw OAuthProviderAlreadyLinked::create();
        }

        $this->oauthIdentities->add(new OAuthIdentity($this, $id, $provider, $externalId, $linkedAt));
    }

    public function unlinkOAuthIdentity(OAuthProvider $provider): void
    {
        $identity = $this->identityFor($provider) ?? throw OAuthProviderNotLinked::create();

        $this->oauthIdentities->removeElement($identity);
    }

    private function identityFor(OAuthProvider $provider): ?OAuthIdentity
    {
        foreach ($this->oauthIdentities as $identity) {
            if ($identity->provider() === $provider) {
                return $identity;
            }
        }

        return null;
    }
}
