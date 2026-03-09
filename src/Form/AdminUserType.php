<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
    use Symfony\Component\Form\Extension\Core\Type\PasswordType;
    use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\CallbackTransformer;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use App\Entity\User;

class AdminUserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('username', TextType::class, [
                'required' => true,
                'disabled' => $isEdit,
                'attr' => ['placeholder' => 'Username'],
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotBlank(['message' => 'Username is required.']),
                    new \Symfony\Component\Validator\Constraints\Length(['max' => 180]),
                ],
            ])
            ->add('email', EmailType::class, [
                'required' => true,
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotBlank(['message' => 'Email is required.']),
                    new \Symfony\Component\Validator\Constraints\Email(['message' => 'Please enter a valid email address.']),
                    new \Symfony\Component\Validator\Constraints\Length(['max' => 180]),
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => !$isEdit,
                'invalid_message' => 'The password fields must match.',
                'first_options'  => ['label' => $isEdit ? 'New password (leave blank to keep existing)' : 'Password'],
                'second_options' => ['label' => $isEdit ? 'Repeat new password' : 'Repeat password'],
                'constraints' => $isEdit ? [] : [
                    new \Symfony\Component\Validator\Constraints\NotBlank(['message' => 'Password is required.']),
                    new \Symfony\Component\Validator\Constraints\Length(['min' => 6, 'minMessage' => 'Password should be at least {{ limit }} characters.']),
                ],
            ])
            ->add('firstName', TextType::class, ['required' => false, 'constraints' => [new \Symfony\Component\Validator\Constraints\Length(['max' => 100])]])
            ->add('lastName', TextType::class, ['required' => false, 'constraints' => [new \Symfony\Component\Validator\Constraints\Length(['max' => 100])]])
            ->add('phone', TextType::class, ['required' => false, 'constraints' => [
                new \Symfony\Component\Validator\Constraints\Length(['max' => 20]),
                new \Symfony\Component\Validator\Constraints\Regex(['pattern' => '/^[0-9+\-\s()]*$/', 'message' => 'Phone may contain only numbers, spaces, parentheses, plus and hyphen.'])
            ]])
            ->add('roles', ChoiceType::class, [
                'choices' => [
                    'Admin' => 'ROLE_ADMIN',
                    'Staff' => 'ROLE_STAFF',
                ],
                'expanded' => false,
                // use single-select dropdown instead of multi-select box to avoid scrolling
                'multiple' => false,
                'required' => true,
                'attr' => ['class' => 'form-select']
            ])
            ->add('isActive', ChoiceType::class, [
                'choices' => [
                    'Active' => true,
                    'Inactive' => false,
                ],
                'expanded' => false,
                'multiple' => false,
                'required' => true,
                'data' => true,
                'label' => 'Status',
                'attr' => ['class' => 'form-select']
            ])
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
            // Submit button is rendered in the Twig template to allow custom styling
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
            'is_edit' => false,
        ]);
    }
}
