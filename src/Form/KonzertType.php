<?php

namespace App\Form;

use App\Entity\Konzert;
use App\Entity\SongKeyword;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class KonzertType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Name des Konzerts',
                'attr'  => ['class' => 'form-control'],
            ])
            ->add('date', DateType::class, [
                'label'  => 'Datum',
                'widget' => 'single_text',
                'attr'   => ['class' => 'form-control'],
            ])
            ->add('location', TextType::class, [
                'label'    => 'Ort',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'z.B. Bürgersaal Willstätt'],
            ])
            ->add('programmVorderseiteFile', FileType::class, [
                'label'    => 'Programm aussen',
                'required' => false,
                'mapped'   => false,
                'attr'     => ['class' => 'form-control', 'accept' => 'image/*,application/pdf'],
            ])
            ->add('programmRueckseiteFile', FileType::class, [
                'label'    => 'Programm innen',
                'required' => false,
                'mapped'   => false,
                'attr'     => ['class' => 'form-control', 'accept' => 'image/*,application/pdf'],
            ])
            ->add('songs', EntityType::class, [
                'label'        => false,
                'class'        => SongKeyword::class,
                'choice_label' => function (SongKeyword $s) {
                    $title = $s->getParent()
                        ? $s->getParent()->getSongName() . ' - ' . $s->getSongName()
                        : $s->getSongName();
                    return ($s->getComposer() ? $s->getComposer() . ' – ' : '') . $title;
                },
                'multiple'      => true,
                'expanded'      => false,
                'required'      => false,
                'by_reference'  => false,
                'query_builder' => fn($repo) => $repo->createQueryBuilder('s')
                    ->leftJoin('s.parent', 'p')
                    ->addSelect('p')
                    ->orderBy('s.songName', 'ASC'),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Konzert::class]);
    }
}
