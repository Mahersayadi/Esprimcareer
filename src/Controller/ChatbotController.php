<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Service\GeminiAPI;

class ChatbotController extends AbstractController
{
    #[Route('/chatbot', name: 'chatbot')]
    public function index(): Response
    {
        // Message de bienvenue initial
        $welcomeMessage = "Bonjour ! Je suis Esprit, votre assistant spécialisé dans les questions d'emploi, de stage et d'entretien. Posez-moi vos questions !";
        
        return $this->render('chatbot/index.html.twig', [
            'welcomeMessage' => $welcomeMessage
        ]);
    }

    #[Route('/chatbot/send', name: 'chatbot_send', methods: ['POST'])]
    public function send(Request $request, GeminiAPI $geminiAPI): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $userMessage = trim($data['message'] ?? '');

        if (empty($userMessage)) {
            return new JsonResponse(['error' => 'Veuillez entrer un message.'], 400);
        }

        try {
            // Instructions plus détaillées pour le chatbot
            $guidance = <<<PROMPT
Tu es Esprit, un assistant conversationnel spécialisé dans les conseils pour les entretiens d'embauche, 
les offres d'emploi et les stages. Ta mission est d'aider les utilisateurs à préparer leurs entretiens, 
rédiger leurs CV et lettres de motivation, et donner des conseils professionnels.

Règles strictes à suivre :
1. Réponds uniquement aux questions liées au monde professionnel (emploi, stage, entretien)
2. Sois bienveillant, professionnel et encourageant
3. Structure tes réponses avec des paragraphes clairs et des listes à puces quand c'est pertinent
4. Si la question n'est pas dans ton domaine, réponds poliment en expliquant ta spécialité

Exemple de réponse idéale :
"Pour préparer un entretien, je vous recommande :
- De bien connaître l'entreprise et son secteur
- De préparer des exemples concrets de vos réalisations
- D'anticiper les questions courantes
- De préparer vos propres questions pour le recruteur"

Question de l'utilisateur : 
PROMPT;

            $fullPrompt = $guidance . $userMessage;
            
            $response = $geminiAPI->getChatResponse($fullPrompt);
            
            return new JsonResponse([
                'response' => $this->formatResponse($response)
            ]);
        } catch (\Exception $e) {
            return new JsonResponse(['error' => 'Désolé, un problème technique est survenu. Veuillez réessayer plus tard.'], 500);
        }
    }

    private function formatResponse(string $response): string
    {
        // Formater les listes pour le HTML
        $response = preg_replace('/\*\*(.*?)\*\*/', '<strong>$1</strong>', $response);
        $response = preg_replace('/\*(.*?)\*/', '<em>$1</em>', $response);
        $response = preg_replace('/- (.*?)(\n|$)/', '• $1<br>', $response);
        
        // Remplacer les sauts de ligne par des balises <br>
        $response = nl2br($response);
        
        return $response;
    }
}