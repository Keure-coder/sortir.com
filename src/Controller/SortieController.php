<?php

namespace App\Controller;

use App\Entity\Etat;
use App\Entity\Lieu;
use App\Entity\Participant;
use App\Entity\Sortie;
use App\Form\LieuType;
use App\Form\SortieType;
use App\Repository\CampusRepository;
use App\Repository\EtatRepository;
use App\Repository\LieuRepository;
use App\Repository\SortieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\ParamConverter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\String\Slugger\SluggerInterface;

class SortieController extends AbstractController
{
    /**
     * @Route("/accueil/sortie" , name="sortie_creation")
     *
     */
    public function creerSortie(
                                 Request $request,
                                 EntityManagerInterface $entityManager,
                                 SluggerInterface $slugger,
                                 SortieRepository $sortieRepository,
                                    CampusRepository $campusRepository
                           )
    {
        $sortie = new Sortie();
        $lieu = new Lieu();
        $paticipant = $this->getUser();
        $campusOrganisateur = $this->getUser()->getCampus();

        $formLieu = $this->createForm(LieuType::class, $lieu);
        $formSortie = $this->createForm(SortieType::class, $sortie);
        $formLieu->handleRequest($request);
        $formSortie->handleRequest($request);

        if($formSortie->isSubmitted() && $formSortie->isValid())
        {
            if ($formSortie->get('photo')->getData() == null)
            {
                $sortie->setPhoto('eni.png');
            }
            $imageFile = $formSortie->get('photo')->getData();
            if ($imageFile)
            {

                $originalImageName = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeImageName =$slugger->slug($originalImageName);
                $imageName = $safeImageName.'-'.uniqid().'.'.$imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('brochures_directory'),
                        $imageName);
                }catch (FileException$e)
                {
                    return $e->getTrace();
                }
                $sortie->setPhoto($imageName);
            }

            $sortie->setDatePublication(new \DateTime());
            $sortie->setOrganisateur($paticipant);
            $sortie->setCampusOrganisateur($campusOrganisateur);
            $entityManager->persist($sortie);
            $entityManager->flush();

            $publier = $request->request->get('publier');
            $sauvegarder = $request->request->get('safe');

            if($publier != null)
            {
                $sorties = $sortieRepository->findAll();
                $this->addFlash('success', 'Votre sortie à bien été publiée');
                return $this->redirectToRoute('main_accueil', [
                    'sorties' => $sorties
                ]);
            }else
            {
                $user = $this->getUser()->getId();
                $sorties = $sortieRepository->mesSortie($user);
                $campus = $campusRepository->findall();
                $this->addFlash('success', 'Votre sortie à bien été sauvegardée');
                return $this->render('main/sortieHistorique.html.twig', [
                    'sorties' => $sorties,
                    'campus' =>$campus
                ]);
            }

        }

        if($formLieu->isSubmitted() && $formLieu->isValid())
        {
            $entityManager->persist($lieu);
            $entityManager->flush();

            $this->addFlash('success', 'Lieu créer');
            return $this->redirectToRoute('sortie_creation');

        }

        return $this->render('main/sortie.html.twig', [
            'formSortie' => $formSortie->createView(),
            'formLieu' =>$formLieu->createView(),
        ]);
    }

    /**
     * @Route("/accueil/mesSortie{id}", name="sortie_historique")
     */
    public function historiqueSortie(int $id,SortieRepository $sortieRepository): Response
    {

        $sorties = $sortieRepository->mesSortie($id);
        return $this->render('main/sortieHistorique.html.twig', [
            'sorties' => $sorties,
        ]);
    }

    /**
     * @Route("/sortie/modifier{id}", name="sortie_modifier")
     *
     */
    public function modifierSortie(int $id, Request $request,
                                    SluggerInterface $slugger,
                                    SortieRepository $sortieRepository,
                                   EntityManagerInterface $entityManager): Response
    {
        $lieu = new Lieu();
        $sortie = $sortieRepository->find($id);
        $formLieu = $this->createForm(LieuType::class, $lieu);
        $formSortie = $this->createForm(SortieType::class, $sortie);
        $formLieu->handleRequest($request);
        $formSortie->handleRequest($request);



        if($formSortie->isSubmitted() && $formSortie->isValid())
        {
            if ($formSortie->get('photo')->getData() == null)
            {
                $sortie->setPhoto('eni.png');
            }
            $imageFile = $formSortie->get('photo')->getData();
            if ($imageFile)
            {

                $originalImageName = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeImageName =$slugger->slug($originalImageName);
                $imageName = $safeImageName.'-'.uniqid().'.'.$imageFile->guessExtension();
                try {
                    $imageFile->move(
                        $this->getParameter('brochures_directory'),
                        $imageName);
                }catch (FileException$e)
                {
                    return $e->getTrace();
                }
                $sortie->setPhoto($imageName);
            }


            $user = $this->getUser()->getId();
            $entityManager->persist($sortie);
            $entityManager->flush();
            $sorties = $sortieRepository->mesSortie($user);
            $this->addFlash('success', 'Votre sortie à bien été modifier');
            return $this->redirectToRoute('sortie_historique', [
                'sorties' => $sorties,
                'id'=>$user
            ]);
        }

        if($formLieu->isSubmitted() && $formLieu->isValid())
        {
            $entityManager->persist($lieu);
            $entityManager->flush();

            $this->addFlash('success', 'Lieu créer');
            return $this->redirectToRoute('sortie_modifier');

        }

        return $this->render('main/modificationSortie.html.twig', [
            'formSortie' => $formSortie->createView(),
            'formLieu' =>$formLieu->createView(),
        ]);


    }

    /**
     * @Route("/sortie/filtre{id}", name="sortie_filtre")
     */
    public function filtrerLesSortie(int $id,SortieRepository $sortieRepository,Request $request):Response
    {
        $recherche = $request->request->get('sortie');
        $sorties = $sortieRepository->mesSortie($id);
        $user = $this->getUser()->getId();
        dump($recherche);
        if($recherche != null)
        {
            dump($recherche);
            $sorties2 = $sortieRepository->sortieTrieeParMot($recherche);
            return $this->render('main/sortieHistorique.html.twig',[
                'sorties' => $sorties2,
                'id' => $user
            ]);
        }
        return $this->render('main/sortieHistorique.html.twig',[
            'sorties' => $sorties,
        ]);

    }
}
