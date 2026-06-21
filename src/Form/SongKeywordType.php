<?php

namespace App\Form;

use App\Entity\SongKeyword;
use App\Entity\Style;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
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
            ->add('etikett', TextType::class, [
                'label'    => 'Etikett (z.B. Rosa 01)',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('isAktuelleProben', CheckboxType::class, [
                'label'    => 'Aktuell in Proben',
                'required' => false,
            ])
            ->add('dropboxlink', TextType::class, [
                'label'    => 'Dropbox-Pfad (Noten)',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => '/Chorgemeinschaft Teutonia/Noten/…'],
            ])
            ->add('aktuelleDropboxlink', TextType::class, [
                'label'    => 'Dropbox-Pfad (Aktuelle Proben)',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => '/Chorgemeinschaft Teutonia/Aktuelle Proben/…'],
                'help'     => 'Nur ausfüllen wenn der Ordner in "Aktuelle Proben" vom Noten-Pfad abweicht.',
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
