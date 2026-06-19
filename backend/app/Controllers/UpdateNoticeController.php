<?php

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Services\UpdateNoticeService;
use Throwable;

final class UpdateNoticeController
{
    public function pending(array $params, array $context): void
    {
        try {
            Response::json((new UpdateNoticeService())->pending((int) $context['user']['id']));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }

    public function acknowledge(array $params, array $context): void
    {
        try {
            $payload = Request::json();
            Response::json((new UpdateNoticeService())->acknowledge((int) $context['user']['id'], (int) ($payload['notice_id'] ?? 0)));
        } catch (Throwable $exception) {
            Response::json(['message' => $exception->getMessage()], 422);
        }
    }
}
