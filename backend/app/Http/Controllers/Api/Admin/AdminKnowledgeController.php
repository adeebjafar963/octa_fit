<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AdminKnowledgeService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Phase 11F — read-only knowledge inspection endpoints.
 */
class AdminKnowledgeController extends Controller
{
    public function __construct(
        private readonly AdminKnowledgeService $knowledge,
    ) {}

    public function sources(): JsonResponse
    {
        return ApiResponse::success(
            data: ['items' => $this->knowledge->listSources()],
            message: 'Knowledge sources',
        );
    }

    public function documents(Request $request): JsonResponse
    {
        $sourceId = $request->query('source_id');
        $sourceId = is_numeric($sourceId) ? (int) $sourceId : null;

        return ApiResponse::success(
            data: ['items' => $this->knowledge->listDocuments($sourceId)],
            message: 'Knowledge documents',
        );
    }

    public function chunks(Request $request): JsonResponse
    {
        $documentId = $request->query('document_id');
        $documentId = is_numeric($documentId) ? (int) $documentId : null;

        return ApiResponse::success(
            data: ['items' => $this->knowledge->listChunks($documentId)],
            message: 'Knowledge chunks',
        );
    }
}
