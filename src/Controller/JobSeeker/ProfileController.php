<?php

namespace App\Controller\JobSeeker;

use App\Entity\User;
use App\Form\JobSeekerProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/job-seeker/profile', name: 'job_seeker_profile_')]
class ProfileController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_JOB_SEEKER');
        
        return $this->render('job_seeker/profile.html.twig', [
            'user' => $this->getUser()
        ]);
    }

    #[Route('/edit', name: 'edit')]
    public function edit(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_JOB_SEEKER');
        
        /** @var User $user */
        $user = $this->getUser();
        $form = $this->createForm(JobSeekerProfileType::class, $user);
        
        $form->handleRequest($request);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($user);
            $em->flush();
            
            $this->addFlash('success', 'Votre profil a été mis à jour avec succès');
            return $this->redirectToRoute('job_seeker_profile_index');
        }
        
        return $this->render('job_seeker/edit_profile.html.twig', [
            'form' => $form->createView(),
            'user' => $user
        ]);
    }
}