<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class JobSearchType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('keywords', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'Job title, keywords...'
                ]
            ])
            ->add('location', TextType::class, [
                'required' => false,
                'attr' => [
                    'placeholder' => 'City, country...'
                ]
            ])
            ->add('submit', SubmitType::class, [
                'label' => 'Search',
                'attr' => [
                    'class' => 'btn-apply'
                ]
            ]);
    }
}