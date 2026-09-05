<?php

declare(strict_types=1);

namespace App\Identity\UI\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/** @extends AbstractType<RegistrationData> */
final class RegistrationForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('email', EmailType::class, [
                'label' => 'auth.email',
                'attr' => ['autocomplete' => 'email', 'autofocus' => true],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'auth.password',
                'attr' => ['autocomplete' => 'new-password'],
                'help' => 'registration.password.help',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => RegistrationData::class,
            'translation_domain' => 'messages',
        ]);
    }
}
