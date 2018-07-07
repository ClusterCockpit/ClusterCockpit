<?php

namespace App\Form;

use App\Entity\StatisticsControl;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class StatisticsControlType extends AbstractType
{
    private function getSystems(){
        return array(
            'emmy' => 1,
            'lima' => 2,
            'meggie' => 3,
            'woody' => 4,
        );
    }

    private function getMonth(){
        return array(
            'January' => '01',
            'February' => '02',
            'March' => '03',
            'April' => '04',
            'Mai' => '05',
            'June' => '06',
            'July' => '07',
            'August' => '08',
            'September' => '09',
            'October' => '10',
            'November' => '11',
            'December' => '12',
        );
    }

    private function getYear(){
        return array(
            '2015' => '2015',
            '2016' => '2016',
            '2017' => '2017',
            '2018' => '2018',
        );
    }


    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('month', ChoiceType::class,array(
                'choices'  => $this->getMonth(),
                'placeholder' => 'All', 'required' => false))
            ->add('year', ChoiceType::class,array(
                'choices'  => $this->getYear(),
                'placeholder' => 'All', 'required' => true))
            ->add('cluster', ChoiceType::class,array(
                'choices'  => $this->getSystems(),
                'placeholder' => 'All', 'required' => true))
            ->add('submit', SubmitType::class, array('label' => 'Update'));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => StatisticsControl::class,
        ]);
    }
}
