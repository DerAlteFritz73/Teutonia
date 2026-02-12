<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\DataMapperInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class FlexibleDateType extends AbstractType implements DataMapperInterface
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->setDataMapper($this);

        $builder
            ->add('year', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'JJJJ',
                    'maxlength' => '4',
                    'list' => 'year-list',
                ],
            ])
            ->add('month', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'MM',
                    'maxlength' => '2',
                    'list' => 'month-list',
                ],
            ])
            ->add('day', TextType::class, [
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'TT',
                    'maxlength' => '2',
                    'list' => 'day-list',
                ],
            ]);
    }

    public function mapDataToForms($viewData, \Traversable $forms): void
    {
        $forms = iterator_to_array($forms);

        // Initialize with empty values
        $forms['year']->setData('');
        $forms['month']->setData('');
        $forms['day']->setData('');

        // Parse the date string and populate the form fields
        if ($viewData) {
            $parts = explode('-', $viewData);
            if (count($parts) >= 1 && $parts[0]) {
                $forms['year']->setData($parts[0]);
            }
            if (count($parts) >= 2 && $parts[1]) {
                $forms['month']->setData($parts[1]);
            }
            if (count($parts) >= 3 && $parts[2]) {
                $forms['day']->setData($parts[2]);
            }
        }
    }

    public function mapFormsToData(\Traversable $forms, &$viewData): void
    {
        $forms = iterator_to_array($forms);

        $year = $forms['year']->getData();
        $month = $forms['month']->getData();
        $day = $forms['day']->getData();

        // Build the date string based on what was provided
        if ($year) {
            if ($month) {
                if ($day) {
                    // Full date: YYYY-MM-DD
                    $viewData = sprintf('%s-%s-%s', $year, $month, $day);
                } else {
                    // Year and month: YYYY-MM
                    $viewData = sprintf('%s-%s', $year, $month);
                }
            } else {
                // Year only: YYYY
                $viewData = $year;
            }
        } else {
            // No date provided
            $viewData = null;
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'compound' => true,
            'inherit_data' => false,
        ]);
    }

    public function getBlockPrefix(): string
    {
        return 'flexible_date';
    }
}
