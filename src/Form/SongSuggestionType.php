<?php

namespace App\Form;

use App\Entity\SongSuggestion;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;

class SongSuggestionType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Songtitel',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. Ave Maria'
                ],
            ])
            ->add('artist', TextType::class, [
                'label' => 'Komponist/Künstler',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'z.B. Franz Schubert'
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Beschreibung / Tips',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'z.B. "Besonders schöne Melodie"'
                ],
            ])
            ->add('link', TextType::class, [
                'label' => 'Link (YouTube o.ä.)',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'https://www.youtube.com/watch?v=…'
                ],
            ])
            ->add('pdfFile', FileType::class, [
                'label' => 'Datei hochladen (PDF, Bild, Audio oder MuseScore)',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'application/pdf,image/*,audio/*,.mscz,.mscx'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '20M',
                        'mimeTypes' => [
                            'application/pdf',
                            'image/*',
                            'audio/*',
                            // MuseScore: .mscz is a zip container, .mscx is plain XML
                            'application/zip',
                            'application/xml',
                            'text/xml',
                        ],
                        'mimeTypesMessage' => 'Bitte laden Sie eine gültige Datei hoch (PDF, Bild, Audio oder MuseScore)',
                    ])
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SongSuggestion::class,
        ]);
    }
}
