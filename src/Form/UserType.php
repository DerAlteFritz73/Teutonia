<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username', TextType::class, [
                'label' => 'Benutzername (für Login)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'z.B. mmueller'],
            ])
            ->add('email', EmailType::class, [
                'label' => 'E-Mail',
                'attr' => ['class' => 'form-control'],
                'required' => false,
            ])
            ->add('firstName', TextType::class, [
                'label' => 'Vorname',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('lastName', TextType::class, [
                'label' => 'Nachname',
                'attr' => ['class' => 'form-control'],
            ])
            ->add('plainPassword', PasswordType::class, [
                'label' => 'Passwort',
                'mapped' => false,
                'required' => $options['is_new'],
                'attr' => ['class' => 'form-control'],
                'help' => $options['is_new'] ? '' : 'Leer lassen, um das Passwort nicht zu ändern.',
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rollen',
                'choices' => [
                    'Mitglied' => 'ROLE_USER',
                    'Administrator' => 'ROLE_ADMIN',
                ],
                'multiple' => true,
                'expanded' => true,
                'attr' => ['class' => 'form-check'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'is_new' => true,
        ]);
    }
}
