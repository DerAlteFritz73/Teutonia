<?php

namespace App\Form;

use App\Entity\SongLink;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Url;

class SongLinkType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('url', TextType::class, [
                'label'       => false,
                'attr'        => ['class' => 'form-control', 'placeholder' => 'https://…'],
                'constraints' => [new NotBlank(), new Url()],
            ])
            ->add('label', TextType::class, [
                'label'    => false,
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Bezeichnung (optional)'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => SongLink::class]);
    }
}
