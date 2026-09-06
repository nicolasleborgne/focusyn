import { Controller } from '@hotwired/stimulus';

/*
 * Abonnement du navigateur aux notifications poussées.
 *
 * Trois choses seulement se passent ici, toutes locales à l'appareil : demander
 * la permission, obtenir un point de réception auprès du service worker, et le
 * confier au serveur. Le reste — savoir quoi envoyer, et quand — appartient au
 * serveur, qui est le seul à connaître les échéances.
 *
 * La permission n'est demandée qu'au clic. Un navigateur refuse désormais toute
 * demande qui ne suit pas un geste, et à juste titre.
 */
export default class extends Controller {
    static targets = ['button', 'status'];
    static values = { publicKey: String, url: String, token: String };

    connect() {
        this.refresh();
    }

    async refresh() {
        if (!this.supported()) {
            this.show('unsupported', false);
            return;
        }

        if (Notification.permission === 'denied') {
            this.show('denied', false);
            return;
        }

        const subscription = await this.current();
        this.show(subscription ? 'on' : 'off', Boolean(subscription));
    }

    async toggle() {
        if (!this.supported()) {
            return;
        }

        const subscription = await this.current();

        if (subscription) {
            await this.send('DELETE', { endpoint: subscription.endpoint });
            await subscription.unsubscribe();
            this.show('off', false);
            return;
        }

        const permission = await Notification.requestPermission();

        if (permission !== 'granted') {
            this.show('denied', false);
            return;
        }

        let fresh;

        try {
            const registration = await navigator.serviceWorker.ready;
            fresh = await registration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: this.decodeKey(this.publicKeyValue),
            });
        } catch (error) {
            // Le service de notification du navigateur peut refuser : hors
            // ligne, derrière un pare-feu, ou simplement indisponible. Sans ce
            // filet, l'utilisateur resterait devant un bouton sans réponse.
            this.show('error', false);
            return;
        }

        const response = await this.send('POST', fresh.toJSON());

        if (!response.ok) {
            // Le serveur n'a pas retenu l'appareil : le garder abonné côté
            // navigateur ferait croire à une notification qui n'arrivera pas.
            await fresh.unsubscribe();
            this.show('error', false);
            return;
        }

        this.show('on', true);
    }

    supported() {
        return 'serviceWorker' in navigator && 'PushManager' in window && 'Notification' in window;
    }

    async current() {
        const registration = await navigator.serviceWorker.ready;

        return registration.pushManager.getSubscription();
    }

    send(method, body) {
        return fetch(this.urlValue, {
            method,
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': this.tokenValue },
            body: JSON.stringify(body),
        });
    }

    show(state, on) {
        this.statusTarget.textContent = this.statusTarget.dataset[state] || '';

        if (this.hasButtonTarget) {
            this.buttonTarget.hidden = state === 'unsupported' || state === 'denied';
            this.buttonTarget.textContent = this.buttonTarget.dataset[on ? 'off' : 'on'];
        }
    }

    /*
     * La clé publique voyage en base64url ; `applicationServerKey` veut des
     * octets.
     */
    decodeKey(key) {
        const padded = (key + '='.repeat((4 - (key.length % 4)) % 4)).replace(/-/g, '+').replace(/_/g, '/');
        const raw = atob(padded);

        return Uint8Array.from([...raw].map((char) => char.charCodeAt(0)));
    }
}
