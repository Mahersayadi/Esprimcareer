<?php

namespace App\Twig;

use App\Repository\ConversationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private ConversationRepository $conversationRepository,
        private Security $security
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('unread_messages_count', [$this, 'getUnreadMessagesCount']),
        ];
    }

    public function getUnreadMessagesCount(): int
    {
        $user = $this->security->getUser();
        return $user ? $this->conversationRepository->getUnreadCount($user) : 0;
    }
}