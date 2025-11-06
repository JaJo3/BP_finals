<?php

namespace App\Form;

use App\Entity\Ticket;
use App\Entity\Event;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class TicketType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            //  Event Field
            ->add('event', EntityType::class, [
                'class' => Event::class,
                'choice_label' => 'eventName',
                'placeholder' => 'Select an event',
                'required' => true,
                'constraints' => [
                    new Assert\NotNull([
                        'message' => 'Please select an event.'
                    ]),
                ],
            ])

            // Ticket Type
            ->add('ticketType', ChoiceType::class, [
                'choices' => [
                    'VVIP' => 'VVIP',
                    'VIP' => 'VIP',
                    'Regular' => 'Regular'
                ],
                'placeholder' => 'Select ticket type',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please select a ticket type.'
                    ]),
                    new Assert\Choice([
                        'choices' => ['VVIP', 'VIP', 'Regular'],
                        'message' => 'Invalid ticket type selected.'
                    ]),
                ],
            ])

            //  Price Field
            ->add('price', MoneyType::class, [
                'currency' => 'PHP',
                'required' => true,
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter a price.'
                    ]),
                    new Assert\Positive([
                        'message' => 'Price must be a positive number.'
                    ]),
                    new Assert\Range([
                        'min' => 5000,
                        'max' => 15000,
                        'notInRangeMessage' => 'Price must be between ₱{{ min }} and ₱{{ max }}.',
                    ]),
                ],
            ])

            // Quantity Field
            ->add('quantity', IntegerType::class, [
                'required' => true,
                'attr' => ['min' => 1, 'max' => 1000],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'Please enter a quantity.'
                    ]),
                    new Assert\Positive([
                        'message' => 'Quantity must be greater than zero.'
                    ]),
                    new Assert\LessThanOrEqual([
                        'value' => 1000,
                        'message' => 'Quantity cannot exceed 1000 tickets.'
                    ]),
                ],
            ])

            // Status Field
            ->add('status', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'Upcoming' => Ticket::STATUS_UPCOMING,
                    'Ongoing' => Ticket::STATUS_ONGOING,
                    'Completed' => Ticket::STATUS_COMPLETED,
                    'Cancelled' => Ticket::STATUS_CANCELLED
                ],
                'attr' => [
                    'class' => 'form-select',
                    'style' => 'border: 2px solid #1A3D7C; border-radius: 8px;'
                ],
                'label_attr' => [
                    'class' => 'form-label fw-bold',
                    'style' => 'color: #1A3D7C;'
                ],
                'choice_attr' => function($choice, $key, $value) {
                    return ['style' => $value === Ticket::STATUS_UPCOMING ? 
                        'background-color: #F9B233; color: #1A3D7C;' : ''];
                },
                'required' => true,
                'placeholder' => 'Select status'
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ticket::class,
        ]);
    }
}
