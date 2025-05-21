<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\Message;
use App\Entity\User;
use App\Form\MessageType;
use App\Repository\ConversationRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Bundle\SecurityBundle\Security; // Ajoutez cette ligne


#[Route('/conversation')]
#[IsGranted('ROLE_USER')]
class ConversationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private ConversationRepository $conversationRepository,
        private UserRepository $userRepository,
        private AuthorizationCheckerInterface $authorizationChecker,
        private HubInterface $hub,
        private Security $security // Ajoutez cette ligne

    ) {
    }

    #[Route('/', name: 'conversation_index', methods: ['GET'])]
    public function index(): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Invalid user type');
        }

        $conversations = $this->conversationRepository->findByParticipant($user);

        return $this->render('conversation/index.html.twig', [
            'conversations' => $conversations,
        ]);
    }

    #[Route('/unread-count', name: 'conversation_unread_count', methods: ['GET'])]
    public function unreadCount(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $count = $user ? $this->conversationRepository->getUnreadCount($user) : 0;
        
        return $this->json(['count' => $count]);
    }

    #[Route('/new', name: 'conversation_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Invalid user type');
        }

        if ($request->isMethod('POST')) {
            $participantIds = $request->request->all('participants');
            
            if (empty($participantIds)) {
                $this->addFlash('error', 'Vous devez sélectionner au moins un participant');
                return $this->redirectToRoute('conversation_new');
            }

            $participants = $this->userRepository->findBy(['id' => $participantIds]);
            
            $existingConversation = $this->conversationRepository->findExistingConversation($user, $participants);
            if ($existingConversation) {
                return $this->redirectToRoute('conversation_show', ['id' => $existingConversation->getId()]);
            }

            $conversation = new Conversation();
            $conversation->addParticipant($user);
            
            foreach ($participants as $participant) {
                if ($participant !== $user) {
                    $conversation->addParticipant($participant);
                }
            }

            $this->entityManager->persist($conversation);
            $this->entityManager->flush();

            return $this->redirectToRoute('conversation_show', ['id' => $conversation->getId()]);
        }

        $users = $this->userRepository->findAllExcept($user);

        return $this->render('conversation/new.html.twig', [
            'users' => $users,
        ]);
    }

    #[Route('/{id}', name: 'conversation_show', methods: ['GET', 'POST'])]
    public function show(
        Conversation $conversation,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('VIEW', $conversation);

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Invalid user type');
        }

        if (!$conversation->getParticipants()->contains($user)) {
            throw new AccessDeniedException('You are not a participant of this conversation');
        }

        $this->markMessagesAsRead($conversation, $user);

        $message = new Message();
        $form = $this->createForm(MessageType::class, $message);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $message
                ->setSender($user)
                ->setConversation($conversation);

            $this->entityManager->persist($message);
            $this->entityManager->flush();

            $this->publishMessageUpdate($conversation, $message, $user);

            return $this->redirectToRoute('conversation_show', ['id' => $conversation->getId()]);
        }

        return $this->render('conversation/show.html.twig', [
            'conversation' => $conversation,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/typing', name: 'conversation_typing', methods: ['POST'])]
    public function typing(Conversation $conversation): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $conversation);

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Invalid user type');
        }

        $update = new Update(
            "/conversation/{$conversation->getId()}",
            json_encode([
                'type' => 'typing',
                'user_id' => $user->getId(),
                'username' => $user->getUsername(),
                'timestamp' => time()
            ])
        );

        $this->hub->publish($update);

        return new Response(null, Response::HTTP_NO_CONTENT);
    }

    #[Route('/{id}/add-participant', name: 'conversation_add_participant', methods: ['POST'])]
    public function addParticipant(
        Conversation $conversation,
        Request $request
    ): Response {
        $this->denyAccessUnlessGranted('EDIT', $conversation);

        $userId = $request->request->get('user_id');
        $user = $this->userRepository->find($userId);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur non trouvé');
            return $this->redirectToRoute('conversation_show', ['id' => $conversation->getId()]);
        }

        if ($conversation->getParticipants()->contains($user)) {
            $this->addFlash('warning', 'Cet utilisateur fait déjà partie de la conversation');
            return $this->redirectToRoute('conversation_show', ['id' => $conversation->getId()]);
        }

        $conversation->addParticipant($user);
        $this->entityManager->flush();

        $this->addFlash('success', 'Participant ajouté avec succès');
        return $this->redirectToRoute('conversation_show', ['id' => $conversation->getId()]);
    }

    #[Route('/{id}/leave', name: 'conversation_leave', methods: ['POST'])]
    public function leave(Conversation $conversation): Response
    {
        $this->denyAccessUnlessGranted('VIEW', $conversation);

        /** @var User $user */
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw new AccessDeniedException('Invalid user type');
        }

        $conversation->removeParticipant($user);
        
        if ($conversation->getParticipants()->count() <= 1) {
            $this->entityManager->remove($conversation);
        }
        
        $this->entityManager->flush();

        return $this->redirectToRoute('conversation_index');
    }

    private function markMessagesAsRead(Conversation $conversation, User $user): void
    {
        foreach ($conversation->getMessages() as $message) {
            if ($message->getSender() !== $user && !$message->getIsRead()) {
                $message->setIsRead(true);
            }
        }
        $this->entityManager->flush();
    }

    private function publishMessageUpdate(Conversation $conversation, Message $message, User $sender): void
    {
        $update = new Update(
            [
                "/conversation/{$conversation->getId()}",
                "/user/{$sender->getId()}"
            ],
            json_encode([
                'type' => 'new_message',
                'id' => $message->getId(),
                'content' => $message->getContent(),
                'sender' => [
                    'id' => $sender->getId(),
                    'username' => $sender->getUsername(),
                    'avatar' => $sender->getProfileImagePath()
                ],
                'conversation_id' => $conversation->getId(),
                'created_at' => $message->getCreatedAt()->format('Y-m-d H:i:s'),
                'unread_count' => $this->conversationRepository->getUnreadCount($sender)
            ]),
            true
        );

        $this->hub->publish($update);
    }
}