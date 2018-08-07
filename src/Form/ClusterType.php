<?php
namespace App\Form;

use App\Entity\Cluster;
use App\Form\MetricListType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\Extension\Core\Type\ButtonType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class ClusterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('name', TextType::class)
            ->add('coresPerNode', IntegerType::class)
            ->add('flopRateScalar', NumberType::class)
            ->add('flopRateSimd', NumberType::class)
            ->add('memoryBandwidth', NumberType::class)
            ->add('save', SubmitType::class, array('label' => 'Save changes'))
            ->add('cancel', SubmitType::class, array('label' => 'Cancel'));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            'data_class' => Cluster::class,
        ));
    }
}

