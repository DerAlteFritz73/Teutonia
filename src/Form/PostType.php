<?php

namespace App\Form;

use App\Entity\Post;
use App\Form\FlexibleDateType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\All;
use Symfony\Component\Validator\Constraints\File;

class PostType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => 'Titel',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Titel des Beitrags'],
            ])
            ->add('subtitle', TextType::class, [
                'label' => 'Untertitel',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'Optionaler Untertitel'],
            ])
            ->add('date', FlexibleDateType::class, [
                'label' => 'Datum',
                'required' => false,
            ])
            ->add('imageFile', FileType::class, [
                'label' => 'Bild hochladen (oder PDF)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '20M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/jpg',
                            'image/png',
                            'image/gif',
                            'image/webp',
                            'application/pdf',
                        ],
                        'mimeTypesMessage' => 'Bitte laden Sie ein gültiges Bild (JPG, PNG, GIF, WebP) oder PDF hoch',
                    ])
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('galleryFiles', FileType::class, [
                'label' => 'Weitere Fotos',
                'mapped' => false,
                'required' => false,
                'multiple' => true,
                'constraints' => [
                    new All([
                        'constraints' => [
                            new File([
                                'maxSize' => '20M',
                                'mimeTypes' => [
                                    'image/jpeg',
                                    'image/jpg',
                                    'image/png',
                                    'image/gif',
                                    'image/webp',
                                    'application/pdf',
                                ],
                                'mimeTypesMessage' => 'Bitte laden Sie ein gültiges Bild (JPG, PNG, GIF, WebP) oder PDF hoch',
                            ]),
                        ],
                    ]),
                ],
                'attr' => ['class' => 'form-control', 'multiple' => 'multiple'],
            ])
            ->add('galleryMode', ChoiceType::class, [
                'label' => 'Weitere Fotos speichern als',
                'mapped' => false,
                'required' => true,
                'expanded' => true,
                'multiple' => false,
                'choices' => [
                    'Galerie in diesem Beitrag' => 'gallery',
                    'Jeweils als eigener Beitrag' => 'separate',
                ],
                'data' => 'gallery',
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('paragraph', TextareaType::class, [
                'label' => 'Text',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 5],
            ])
            ->add('layout', ChoiceType::class, [
                'label' => 'Layout',
                'choices' => [
                    'Zwei nebeneinander' => 'side_by_side',
                    'Einer pro Zeile' => 'single_row',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('isMain', CheckboxType::class, [
                'label' => 'Als Hauptbeitrag markieren (bleibt oben)',
                'required' => false,
                'attr' => ['class' => 'form-check-input'],
            ])
            ->add('page', ChoiceType::class, [
                'label' => 'Seite',
                'choices' => [
                    'Unser Chor' => 'unser-chor',
                    'Konzerte und Aktivitäten' => 'konzerte-und-aktivitaeten',
                    'Historie' => 'historie',
                    'Chorproben' => 'chorproben',
                    'Unser Repertoire' => 'unser-repertoire',
                    'Unsere nächsten Termine' => 'unsere-naechsten-termine',
                    'Geselliges' => 'geselliges',
                    'Archiv' => 'archiv',
                    'Beiträge (Übersichtsseite)' => 'beitraege',
                ],
                'attr' => ['class' => 'form-select'],
            ])
            ->add('fontTitle', HiddenType::class, ['required' => false])
            ->add('fontTitleSize', HiddenType::class, ['required' => false])
            ->add('fontTitleBold', CheckboxType::class, ['required' => false, 'mapped' => true, 'attr' => ['class' => 'd-none']])
            ->add('fontTitleItalic', CheckboxType::class, ['required' => false, 'mapped' => true, 'attr' => ['class' => 'd-none']])
            ->add('fontTitleUnderline', CheckboxType::class, ['required' => false, 'mapped' => true, 'attr' => ['class' => 'd-none']])
            ->add('fontSubtitle', HiddenType::class, ['required' => false])
            ->add('fontSubtitleSize', HiddenType::class, ['required' => false])
            ->add('fontSubtitleBold', CheckboxType::class, ['required' => false, 'mapped' => true, 'attr' => ['class' => 'd-none']])
            ->add('fontSubtitleItalic', CheckboxType::class, ['required' => false, 'mapped' => true, 'attr' => ['class' => 'd-none']])
            ->add('fontSubtitleUnderline', CheckboxType::class, ['required' => false, 'mapped' => true, 'attr' => ['class' => 'd-none']])
            ->add('fontParagraph', HiddenType::class, ['required' => false])
            ->add('fontParagraphSize', HiddenType::class, ['required' => false])
            ->add('fontParagraphBold', CheckboxType::class, ['required' => false, 'mapped' => true, 'attr' => ['class' => 'd-none']])
            ->add('fontParagraphItalic', CheckboxType::class, ['required' => false, 'mapped' => true, 'attr' => ['class' => 'd-none']])
            ->add('fontParagraphUnderline', CheckboxType::class, ['required' => false, 'mapped' => true, 'attr' => ['class' => 'd-none']])
            ->add('imageRotation', HiddenType::class, ['required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Post::class,
        ]);
    }
}
