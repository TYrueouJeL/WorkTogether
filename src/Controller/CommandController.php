<?php

namespace App\Controller;

use App\Entity\CommandedUnit;
use App\Entity\Order;
use App\Entity\Pack;
use App\Form\OrderReservationFormType;
use App\Repository\PackRepository;
use App\Repository\UnitRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Finder\Exception\AccessDeniedException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class CommandController extends AbstractController
{
    #[Route('/command/{id}', name: 'app_command_reservation')]
    public function commandReservation(EntityManagerInterface $entityManager, int $id, PackRepository $packRepository, Request $request, Pack $pack, UnitRepository $unitRepository): Response
    {
        $user = $this->getUser();

        if ($user == null)
        {
            return $this->redirect('/login');
        }

        $order = new Order();
        $form = $this->createForm(OrderReservationFormType::class, $order);

        $form->handleRequest($request);

        $numberOfUnits = $pack->getNbrUnits();

        $availableUnits = $unitRepository->findAvailableUnits($numberOfUnits);

        if (count($availableUnits) < $numberOfUnits) {
            return $this->render('command/not_enough_units.html.twig', [
                'requiredUnits' => $numberOfUnits,
                'availableUnits' => count($availableUnits),
            ]);
        }

        // Réception du formulaire
        if ($form->isSubmitted() && $form->isValid()) {
            $subscriptionTime = $form->get('subscriptionTime')->getData();

            $startDate = new \DateTime();
            $order->setStartDate($startDate);
            $endDate = (clone $startDate)->modify('+' . $subscriptionTime . ' month');
            $order->setEndDate($endDate);

            $order->setDuration($subscriptionTime);

            if ($subscriptionTime >= 12) {
                $order->setAnnual(true);
                $order->setPrice($subscriptionTime * $pack->getPrice() * (10 - $pack->getAnnualReductionPercentage() /10) /10);
            }
            else{
                $order->setAnnual(false);
                $order->setPrice($subscriptionTime * $pack->getPrice());
            }

            // Définition du pack avec le paramètre de l'url
            $pack = $packRepository->find($id);

            $order->setPack($pack);
            $order->setCustomer($user);

            for ($i = 1; $i <= $numberOfUnits; $i++) {
                $commandedUnit = new CommandedUnit();
                $commandedUnit->setOrders($order);
                $unit = $unitRepository->find($availableUnits[$i - 1]);
                $commandedUnit->setUnit($unit);
                $commandedUnitTab[] = $commandedUnit;
            }

            foreach ($commandedUnitTab as $commandedUnitItem) {
                $entityManager->persist($commandedUnitItem);
            }

            $entityManager->persist($order);
            $entityManager->flush();

            return $this->redirectToRoute('app_home_page');
        }

        return $this->render('command/reservation.html.twig', [
            'form' => $form,
            'pack' => $packRepository->find($id),
            'availableUnits' => $availableUnits,
        ]);
    }
}
