<?php

namespace App\Service;

use App\Entity\Task;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class TaskAssignmentService
{
     public function __construct(private EntityManagerInterface $em) {}
    public function assignDailyTasks($data, $form, EntityManagerInterface $entityManager)
    {
            $date = $data['date'];

            $assignments = [
                'listingmatin' => $form->get('listingmatin')->getData(),
                'cantine' => $form->get('cantine')->getData(),
                'listingmidi' => $form->get('listingmidi')->getData(),
                'gouter' => $form->get('gouter')->getData(),
                'vaisselle' => $form->get('vaisselle')->getData(),
                'listingsoir' => $form->get('listingsoir')->getData(),
            ];

            foreach ($assignments as $type => $assignedUser) {
                $assignedUsers = $form->get($type)->getData(); // ArrayCollection
                if ($assignedUsers && count($assignedUsers) > 0) {
                    $task = new Task();
                    $task->setTitle(\App\Enum\TaskType::from($type));
                    foreach ($assignedUsers as $assignedUser) {
                        $task->addUser($assignedUser);
                    }
                    $task->setDate($date);
                    $entityManager->persist($task);
                }
            }

    }
}