<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

class ChartService
{
    public function __construct(private EntityManagerInterface $em) {}
    public function createChart($countKids3To5, $countKids6To12, ChartBuilderInterface $chartBuilder):Chart
    {
        $chart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);

        $chart->setData([
            'labels' => ['3 - 5 ans', '6 - 12 ans'],
            'datasets' => [[
                'label' => 'Enfants par tranche',
                'data' => [$countKids3To5, $countKids6To12],
                'backgroundColor' => ['#35a1eb', '#41b983'],
            ]]
        ]);
        $chart->setOptions([
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'enabled' => false,
                ],
            ],
        ]);
        return $chart;
    }
}
