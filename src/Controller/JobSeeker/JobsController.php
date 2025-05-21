<?php

namespace App\Controller\JobSeeker;

use App\Entity\Job;
use App\Entity\SavedJob;
use App\Entity\JobApplication;
use App\Form\JobSearchType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/job-seeker/jobs', name: 'job_seeker_jobs_')]
class JobsController extends AbstractController
{
    #[Route('/', name: 'index')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_JOB_SEEKER');
        
        $form = $this->createForm(JobSearchType::class);
        $form->handleRequest($request);
        
        $query = $em->getRepository(Job::class)->createQueryBuilder('j')
            ->where('j.status = :status')
            ->setParameter('status', Job::STATUS_ACTIVE);
        
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            
            if ($data['keywords']) {
                $query->andWhere('j.title LIKE :keywords OR j.description LIKE :keywords')
                    ->setParameter('keywords', '%'.$data['keywords'].'%');
            }
            
            if ($data['location']) {
                $query->andWhere('j.location LIKE :location')
                    ->setParameter('location', '%'.$data['location'].'%');
            }
        }
        
        $jobs = $query->getQuery()->getResult();
        $savedJobs = $em->getRepository(SavedJob::class)
            ->findBy(['user' => $this->getUser()]);
        
        return $this->render('job_seeker/job_search.html.twig', [
            'jobs' => $jobs,
            'savedJobs' => $savedJobs,
            'form' => $form->createView()
        ]);
    }

    #[Route('/saved', name: 'saved')]
    public function saved(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_JOB_SEEKER');
        
        $savedJobs = $em->getRepository(SavedJob::class)
            ->findBy(['user' => $this->getUser()], ['savedAt' => 'DESC']);
        
        return $this->render('job_seeker/saved_jobs.html.twig', [
            'savedJobs' => $savedJobs
        ]);
    }

    #[Route('/apply/{id}', name: 'apply')]
    public function apply(Job $job, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_JOB_SEEKER');
        
        // Check if already applied
        $existingApplication = $em->getRepository(JobApplication::class)->findOneBy([
            'user' => $this->getUser(),
            'job' => $job
        ]);
        
        if ($existingApplication) {
            $this->addFlash('warning', 'You have already applied to this job');
            return $this->redirectToRoute('job_seeker_jobs_index');
        }
        
        $application = new JobApplication();
        $application->setUser($this->getUser());
        $application->setJob($job);
        $application->setAppliedAt(new \DateTime());
        $application->setStatus(JobApplication::STATUS_PENDING);
        
        $em->persist($application);
        $em->flush();
        
        $this->addFlash('success', 'Application submitted successfully');
        return $this->redirectToRoute('job_seeker_jobs_index');
    }

    #[Route('/save/{id}', name: 'save')]
    public function save(Job $job, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_JOB_SEEKER');
        
        // Check if already saved
        $existingSavedJob = $em->getRepository(SavedJob::class)->findOneBy([
            'user' => $this->getUser(),
            'job' => $job
        ]);
        
        if ($existingSavedJob) {
            $this->addFlash('warning', 'This job is already in your saved list');
            return $this->redirectToRoute('job_seeker_jobs_index');
        }
        
        $savedJob = new SavedJob();
        $savedJob->setUser($this->getUser());
        $savedJob->setJob($job);
        $savedJob->setSavedAt(new \DateTime());
        
        $em->persist($savedJob);
        $em->flush();
        
        $this->addFlash('success', 'Job saved to your list');
        return $this->redirectToRoute('job_seeker_jobs_index');
    }

    #[Route('/unsave/{id}', name: 'unsave')]
    public function unsave(Job $job, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_JOB_SEEKER');
        
        $savedJob = $em->getRepository(SavedJob::class)->findOneBy([
            'user' => $this->getUser(),
            'job' => $job
        ]);
        
        if ($savedJob) {
            $em->remove($savedJob);
            $em->flush();
            $this->addFlash('success', 'Job removed from your saved list');
        }
        
        return $this->redirectToRoute('job_seeker_jobs_saved');
    }
}