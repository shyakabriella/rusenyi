<?php

namespace App\Http\Controllers\API\Report;

use App\Http\Controllers\API\BaseController;
use App\Http\Requests\API\Report\ReportFilterRequest;
use App\Services\Reports\ReportService;
use Illuminate\Http\JsonResponse;

class ReportController extends BaseController
{
    public function __construct(
        private ReportService $reports
    ) {
    }

    public function overview(
        ReportFilterRequest $request
    ): JsonResponse {
        return $this->sendResponse(
            $this->reports->overview(
                $request->date_from,
                $request->date_to
            ),
            'Report overview retrieved successfully.'
        );
    }

    public function finance(
        ReportFilterRequest $request
    ): JsonResponse {
        return $this->sendResponse(
            $this->reports->finance(
                $request->date_from,
                $request->date_to
            ),
            'Finance report retrieved successfully.'
        );
    }

    public function payroll(
        ReportFilterRequest $request
    ): JsonResponse {
        return $this->sendResponse(
            $this->reports->payroll(
                $request->date_from,
                $request->date_to
            ),
            'Payroll report retrieved successfully.'
        );
    }

    public function approvals(
        ReportFilterRequest $request
    ): JsonResponse {
        return $this->sendResponse(
            $this->reports->approvals(
                $request->date_from,
                $request->date_to
            ),
            'Approval report retrieved successfully.'
        );
    }

    public function operations(
        ReportFilterRequest $request
    ): JsonResponse {
        return $this->sendResponse(
            $this->reports->operations(
                $request->date_from,
                $request->date_to
            ),
            'Coffee operations report retrieved successfully.'
        );
    }
}
