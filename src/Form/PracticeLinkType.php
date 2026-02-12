<?php

namespace App\Form;

use App\Entity\PracticeLink;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PracticeLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('song', TextType::class, [
                'label' => 'Lied',
                'attr' => ['class' => 'form-control', 'placeholder' => 'z.B. Hallelujah'],
            ])
            ->add('title', TextType::class, [
                'label' => 'Titel/Dateiname',
                'attr' => ['class' => 'form-control', 'placeholder' => 'z.B. Hallelujah.pdf'],
            ])
            ->add('url', UrlType::class, [
                'label' => 'URL',
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://...'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Typ',
                'choices' => [
                    'PDF' => 'pdf',
                    'MP3' => 'mp3',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('voicePart', ChoiceType::class, [
                'label' => 'Stimme',
                'required' => false,
                'placeholder' => '-- Keine (für PDF) --',
                'choices' => [
                    'Sopran' => 'sopran',
                    'Alt' => 'alt',
                    'Tenor' => 'tenor',
                    'Bass' => 'bass',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('sortOrder', IntegerType::class, [
                'label' => 'Sortierung',
                'attr' => ['class' => 'form-control'],
                'help' => 'Kleinere Zahlen werden zuerst angezeigt.',
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => PracticeLink::class,
        ]);
    }
}
