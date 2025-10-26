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
    public function createChartDoughnut($countKids3To5, $countKids6To12, ChartBuilderInterface $chartBuilder): Chart
    {
        $chart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT);

        $chart->setData([
            'labels' => ['3 - 5 ans', '6 - 12 ans'],
            'datasets' => [[
                'label' => 'Groupes d\'enfants',
                'data' => [$countKids3To5, $countKids6To12],
                'backgroundColor' => ['#35a1eb', '#41b983'],
            ]]
        ]);
        return $chart;
    }

    public function createChartBar(ChartBuilderInterface $chartBuilder)
    {
        $repo = $this->em->getRepository(\App\Entity\Kid::class);
        $data = $repo->countKidsByAge();

        $ages = array_column($data, 'age');
        $counts = array_column($data, 'count');

        $chart = $chartBuilder->createChart(Chart::TYPE_BAR);
        $chart->setData([
            'labels' => $ages,
            'datasets' => [[
                'label' => 'Nombre d\'enfants par âge',
                'data' => $counts,
                'backgroundColor' => [
                    'rgba(255, 99, 132, 0.2)',
                    'rgba(255, 159, 64, 0.2)',
                    'rgba(255, 205, 86, 0.2)',
                    'rgba(75, 192, 192, 0.2)',
                    'rgba(54, 162, 235, 0.2)',
                    'rgba(153, 102, 255, 0.2)',
                    'rgba(201, 203, 207, 0.2)'
                ],
                'borderColor' => [
                    'rgb(255, 99, 132)',
                    'rgb(255, 159, 64)',
                    'rgb(255, 205, 86)',
                    'rgb(75, 192, 192)',
                    'rgb(54, 162, 235)',
                    'rgb(153, 102, 255)',
                    'rgb(201, 203, 207)'
                ],
            ]],

        ]);

        $chart->setOptions([
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ]);

        return $chart;
    }
}
