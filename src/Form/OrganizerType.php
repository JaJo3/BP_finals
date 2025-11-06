<?php

namespace App\Form;

use App\Entity\Organizer;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Regex;

class OrganizerType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            // ORGANIZATION NAME
            ->add('org_name', TextType::class, [
                'label' => 'Organization Name',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter organization name'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Organization name is required']),
                    new Length([
                        'min' => 2,
                        'max' => 255,
                        'minMessage' => 'Organization name must be at least {{ limit }} characters long',
                        'maxMessage' => 'Organization name cannot exceed {{ limit }} characters'
                    ]),
                    new Regex([
                        'pattern' => '/^[A-Za-z0-9\s\.\,\-\&]+$/',
                        'message' => 'Organization name can only contain letters, numbers, spaces, and basic punctuation.'
                    ]),
                ]
            ])

            //CONTACT NUMBER
            ->add('contact', TextType::class, [
                'label' => 'Contact Number',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => '09XXXXXXXXX',
                    'pattern' => '^09\d{9}$',
                    'maxlength' => 11,
                    'title' => 'Contact number must be 11 digits and start with 09'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Contact number is required']),
                    new Regex([
                        'pattern' => '/^09\d{9}$/',
                        'message' => 'Contact number must be 11 digits and start with 09.'
                    ]),
                ]
            ])

            //EMAIL ADDRESS
            ->add('email', EmailType::class, [
                'label' => 'Email Address',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'example@domain.com'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Email address is required']),
                    new Email(['message' => 'Please enter a valid email address']),
                    new Length(['max' => 180])
                ]
            ])

            //CONTACT PERSON
            ->add('contactPerson', TextType::class, [
                'label' => 'Contact Person',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Enter full name of contact person'
                ],
                'constraints' => [
                    new NotBlank(['message' => 'Contact person name is required']),
                    new Length([
                        'min' => 2,
                        'max' => 100,
                        'minMessage' => 'Name must be at least {{ limit }} characters long',
                        'maxMessage' => 'Name cannot exceed {{ limit }} characters'
                    ]),
                    new Regex([
                        'pattern' => '/^[A-Za-z\s\.\-]+$/',
                        'message' => 'Contact person name can only contain letters, spaces, dots, and hyphens.'
                    ]),
                ]
            ])

            //DESCRIPTION
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Describe your organization briefly (optional)'
                ],
                'constraints' => [
                    new Length([
                        'max' => 1000,
                        'maxMessage' => 'Description cannot exceed {{ limit }} characters'
                    ])
                ]
            ])

            //LOGO FILE
            ->add('logoFile', FileType::class, [
                'label' => 'Organization Logo',
                'mapped' => false,
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'accept' => 'image/jpeg,image/png'
                ],
                'constraints' => [
                    new File([
                        'maxSize' => '3M',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid JPG or PNG image.',
                        'maxSizeMessage' => 'The file is too large ({{ size }} {{ suffix }}). Maximum size is {{ limit }} {{ suffix }}.'
                    ])
                ],
                'help' => 'Upload a JPG or PNG image (max 3MB)'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Organizer::class,
        ]);
    }
}
