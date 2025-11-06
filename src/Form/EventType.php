<?php

namespace App\Form;

use App\Entity\Event;
use App\Entity\Organizer;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File; 

class EventType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('eventName', TextType::class)
            ->add('description', TextareaType::class)
            ->add('date', DateTimeType::class, [
                'widget' => 'single_text'
            ])
            ->add('venue', TextType::class)
            ->add('category', ChoiceType::class, [
                'choices' => [
                    'Solo Concert' => 'Solo Concert',
                    'Music Festival' => 'Music Festival'
                ],
                'placeholder' => 'Select event category',
                'required' => true,
            ])
            ->add('status', ChoiceType::class, [
                'choices' => [
                    'Upcoming' => Event::STATUS_UPCOMING,
                    'Ongoing' => Event::STATUS_ONGOING,
                    'Completed' => Event::STATUS_COMPLETED,
                    'Cancelled' => Event::STATUS_CANCELLED
                ],
                'data' => Event::STATUS_UPCOMING,
                'required' => true,
                'attr' => [
                    'class' => 'form-control'
                ]
            ])
            ->add('poster', FileType::class, [
                'label' => 'Event Poster',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2048k',
                        'mimeTypes' => [
                            'image/jpeg',
                            'image/png',
                        ],
                        'mimeTypesMessage' => 'Please upload a valid image file (JPG or PNG)',
                    ])
                ],
            ])
        ;

        $event = $builder->getData();
        $isOrganizerPreselected = $event && $event->getOrganizer() !== null;

        $builder->add('organizer', EntityType::class, [
            'class' => Organizer::class,
            'choice_label' => 'orgName',
            'placeholder' => 'Select an organizer',
            'disabled' => $isOrganizerPreselected,
            'attr' => [
                'class' => 'form-control' . ($isOrganizerPreselected ? ' bg-light' : '')
            ]
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Event::class,
        ]);
    }
}
