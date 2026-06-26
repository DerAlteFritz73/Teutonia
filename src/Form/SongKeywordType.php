<?php

namespace App\Form;

use App\Entity\SongKeyword;
use App\Entity\Style;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class SongKeywordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('songName', TextType::class, [
                'label' => 'Liedtitel',
                'attr'  => ['class' => 'form-control'],
            ])
            ->add('composer', TextType::class, [
                'label'    => 'Komponist',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('arrangeur', TextType::class, [
                'label'    => 'Arrangeur',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('compositionYear', TextType::class, [
                'label'    => 'Kompositionsjahr',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'z.B. 1750 oder 16.Jh.'],
            ])
            ->add('background', TextareaType::class, [
                'label'    => 'Hintergrund',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 4,
                               'placeholder' => 'Hintergrundinfos zum Lied (Geschichte, Kontext, Komponist…)'],
            ])
            ->add('duration', TextType::class, [
                'label'    => 'Dauer',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'M:SS'],
            ])
            ->add('etikettColor', TextType::class, [
                'label'    => 'Etikett',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'list' => 'etikett-colors', 'placeholder' => 'z.B. Rosa'],
            ])
            ->add('etikettNumber', TextType::class, [
                'label'      => 'Nummer',
                'label_attr' => ['class' => 'invisible'],
                'required'   => false,
                'attr'       => ['class' => 'form-control', 'placeholder' => 'z.B. 01'],
            ])
            ->add('isAktuelleProben', CheckboxType::class, [
                'label'    => 'Aktuell in Proben',
                'required' => false,
            ])
            ->add('links', CollectionType::class, [
                'label'         => false,
                'entry_type'    => SongLinkType::class,
                'allow_add'     => true,
                'allow_delete'  => true,
                'by_reference'  => false,
                'required'      => false,
            ])
            ->add('styles', EntityType::class, [
                'label'        => 'Stile',
                'class'        => Style::class,
                'choice_label' => 'name',
                'multiple'     => true,
                'expanded'     => true,
                'required'     => false,
                'by_reference' => false,
            ])
            ->add('parent', EntityType::class, [
                'label'         => 'Übergeordnetes Stück (Satz von)',
                'class'         => SongKeyword::class,
                'choice_label'  => fn(SongKeyword $s) => ($s->getComposer() ? $s->getComposer() . ' – ' : '') . $s->getSongName(),
                'choice_attr'   => fn(SongKeyword $s) => [
                    'data-composer' => $s->getComposer() ?? '',
                    'data-styles'   => json_encode($s->getStyles()->map(fn($st) => $st->getId())->toArray()),
                ],
                'required'      => false,
                'placeholder'   => '— eigenständiges Stück —',
                'attr'          => ['class' => 'form-select'],
                'query_builder' => function ($repo) use ($options) {
                    $qb = $repo->createQueryBuilder('s')
                        ->andWhere('s.parent IS NULL')
                        ->orderBy('s.songName', 'ASC');
                    if ($options['exclude_id']) {
                        $qb->andWhere('s.id != :excl')
                           ->setParameter('excl', $options['exclude_id']);
                    }
                    return $qb;
                },
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => SongKeyword::class,
            'exclude_id' => null,
        ]);
    }
}
