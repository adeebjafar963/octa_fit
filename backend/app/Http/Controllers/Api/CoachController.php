<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Coach\SendCoachMessageRequest;
use App\Http\Requests\Coach\StoreConversationRequest;
use App\Http\Resources\CoachConversationResource;
use App\Http\Resources\CoachMessageResource;
use App\Models\CoachConversation;
use App\Services\CoachService;
use App\Support\ApiResponse;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class CoachController extends Controller
{
    public function __construct(
        private readonly CoachService $coachService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->coachService->listConversations(
            $request->user(),
            (int) $request->query('per_page', 20),
        );

        return ApiResponse::success(
            data: [
                'items' => CoachConversationResource::collection($paginator->items())->resolve(),
                'meta' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                ],
            ],
            message: 'Coach conversations retrieved',
        );
    }

    public function store(StoreConversationRequest $request): JsonResponse
    {
        $conversation = $this->coachService->createConversation(
            $request->user(),
            $request->validated('title'),
        );

        return ApiResponse::success(
            data: (new CoachConversationResource($conversation))->resolve(),
            message: 'Conversation created',
            status: 201,
        );
    }

    public function show(Request $request, CoachConversation $conversation): JsonResponse
    {
        $this->authorize('view', $conversation);

        $model = $this->coachService->findForUser($request->user(), $conversation->id);
        if ($model === null) {
            return ApiResponse::error('Conversation not found', 404);
        }

        return ApiResponse::success(
            data: (new CoachConversationResource($model))->resolve(),
            message: 'Conversation retrieved',
        );
    }

    public function destroy(CoachConversation $conversation): JsonResponse
    {
        $this->authorize('delete', $conversation);
        $this->coachService->deleteConversation($conversation);

        return ApiResponse::success(message: 'Conversation deleted');
    }

    public function sendMessage(SendCoachMessageRequest $request, CoachConversation $conversation): JsonResponse
    {
        $this->authorize('update', $conversation);

        try {
            $result = $this->coachService->sendMessage(
                $request->user(),
                $conversation,
                $request->validated('message'),
                $request->validated('language'),
            );
        } catch (QueryException $e) {
            // Do not mask DB/schema failures as an upstream AI outage.
            Log::error('coach.send_message_query_failed', [
                'user_id' => $request->user()?->id,
                'conversation_id' => $conversation->id,
                'error_class' => class_basename($e),
            ]);

            return ApiResponse::error('Unable to process Coach request right now.', 500);
        } catch (RuntimeException $e) {
            // Never expose provider internals / stack details to clients.
            Log::warning('coach.send_message_failed', [
                'user_id' => $request->user()?->id,
                'conversation_id' => $conversation->id,
                'error_class' => class_basename($e),
            ]);

            return ApiResponse::error('Unable to reach the AI Coach service.', 502);
        }

        return ApiResponse::success(
            data: [
                'user_message' => (new CoachMessageResource($result['user_message']))->resolve(),
                'assistant_message' => (new CoachMessageResource($result['assistant_message']))->resolve(),
                'conversation' => (new CoachConversationResource($result['conversation']))->resolve(),
                'grounded_exercises' => $result['grounded_exercises'] ?? [],
                'training_plan' => $result['training_plan'] ?? null,
                'nutrition' => $result['nutrition'] ?? null,
                'nutrition_plan' => $result['nutrition_plan'] ?? null,
                'knowledge_context' => $result['knowledge_context'] ?? null,
            ],
            message: 'Message sent',
            status: 201,
        );
    }
}
