<?php

namespace App\Form;

use App\Entity\User;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Validator\Constraints\File;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('username')
            ->add('email')
            ->add('firstName')
            ->add('lastName')
            ->add('phone')
            ->add('profileImage', FileType::class, [
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/pjpeg', 'image/jfif', 'image/png', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image (jpg, png, webp, jfif).',
                    ])
                ],
            ])
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Staff' => 'ROLE_STAFF',
                ],
                'expanded' => false,
                'multiple' => false,
                'label' => 'Roles',
                'attr' => ['class' => 'form-select'],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'invalid_message' => 'The password fields must match.',
                'first_options'  => ['label' => 'Password', 'attr' => ['class' => 'form-control']],
                'second_options' => ['label' => 'Repeat password', 'attr' => ['class' => 'form-control']],
            ])
        ;

        // transform between the array stored on the User entity and the single value used in the form
        $builder->get('roles')
            ->addModelTransformer(new CallbackTransformer(
                // transform the array (model) to a single value (view)
                function ($rolesArray) {
                    if (is_array($rolesArray) && count($rolesArray) > 0) {
                        return $rolesArray[0];
                    }
                    return null;
                },
                // transform the single value (view) back to an array (model)
                function ($roleString) {
                    if (null === $roleString || '' === $roleString) {
                        return [];
                    }
                    return [$roleString];
                }
            ));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
        ]);
    }
}
