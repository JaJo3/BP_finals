<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

class AdminProfileType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('firstName', TextType::class, [
                'required' => true,
                'label' => 'First name',
                'constraints' => [
                    new NotBlank(['message' => 'First name is required.']),
                    new Length(['max' => 100, 'maxMessage' => 'First name cannot be longer than {{ limit }} characters.'])
                ],
            ])
            ->add('lastName', TextType::class, [
                'required' => false,
                'label' => 'Last name',
                'constraints' => [
                    new Length(['max' => 100, 'maxMessage' => 'Last name cannot be longer than {{ limit }} characters.'])
                ],
            ])
            ->add('phone', TextType::class, [
                'required' => false,
                'label' => 'Phone',
                'constraints' => [
                    new Regex([
                        'pattern' => '/^$|^\+?[0-9\s\-\(\)]{7,20}$/',
                        'message' => 'Please enter a valid phone number (digits, spaces, +, -, parentheses).'
                    ])
                ],
            ])
            ->add('profileImage', FileType::class, [
                'label' => 'Profile image (JPG or PNG)',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2048k',
                        'mimeTypes' => ['image/jpeg', 'image/png'],
                        'mimeTypesMessage' => 'Please upload a valid JPG or PNG image',
                    ])
                ],
            ])
            ->add('save', SubmitType::class, ['label' => 'Save profile', 'attr' => ['class' => 'btn btn-beatpass-primary']])
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([]);
    }
}
