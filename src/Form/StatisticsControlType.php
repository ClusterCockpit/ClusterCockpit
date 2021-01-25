<?php
/*
 *  This file is part of ClusterCockpit.
 *
 *  Copyright (c) 2018 Jan Eitzinger
 *
 *  Permission is hereby granted, free of charge, to any person obtaining a copy
 *  of this software and associated documentation files (the "Software"), to deal
 *  in the Software without restriction, including without limitation the rights
 *  to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 *  copies of the Software, and to permit persons to whom the Software is furnished
 *  to do so, subject to the following conditions:
 *
 *  The above copyright notice and this permission notice shall be included in all
 *  copies or substantial portions of the Software.
 *
 *  THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 *  IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 *  FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 *  AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 *  LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 *  OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 *  THE SOFTWARE.
 */

namespace App\Form;

use App\Entity\StatisticsControl;
use App\Form\Type\DateTimePickerType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Doctrine\ORM\EntityManagerInterface;

class StatisticsControlType extends AbstractType
{
    public function __construct(
        EntityManagerInterface $em
    )
    {
        $this->_clusterRepository = $em->getRepository(\App\Entity\Cluster::class);
    }


    private function getSystems(){
        $clusters = $this->_clusterRepository->findAll();

        foreach  ( $clusters as $cluster ){
            $systems[$cluster->getName()] = $cluster->getId();
        }

	if(isset($systems))
        {
	    return $systems;
	} else
	{
            return NULL;
	}
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
            '2018' => '2018',
            '2019' => '2019',
            '2020' => '2020',
            '2021' => '2021',
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
                'choices'  => $this->getSystems(), 'required' => true))
            ->add('submit', SubmitType::class, array('label' => 'Update'));
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults([
            'data_class' => StatisticsControl::class,
        ]);
    }
}
