<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProjectResource;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * An engagement grouping related tickets and work orders. Mirrors
 * Admin\ProjectController: full CRUD plus a status control.
 */
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::with(['customer', 'assignee'])
            ->withCount(['tickets', 'workOrders'])
            ->search($request->query('q'))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->latest()
            ->paginate($this->perPage($request));

        return ProjectResource::collection($projects);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $project = DB::transaction(function () use ($data) {
            $project = Project::create($data);
            $project->recordActivity('created', 'Project Created');

            return $project;
        });

        return (new ProjectResource($project->load(['customer', 'assignee'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Project $project)
    {
        return new ProjectResource($project->load([
            'customer', 'assignee', 'tickets', 'workOrders',
        ]));
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validated($request, $project);

        DB::transaction(function () use ($project, $data) {
            $project->forceFill($data)->save();
            $project->recordActivity('note', 'Project Updated');
        });

        return new ProjectResource($project->load(['customer', 'assignee']));
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return response()->noContent();
    }

    public function status(Request $request, Project $project)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(Project::STATUSES)],
        ]);

        DB::transaction(function () use ($project, $data) {
            $attrs = ['status' => $data['status']];

            if ($data['status'] === 'completed' && ! $project->completed_at) {
                $attrs['completed_at'] = now();
            }

            $project->forceFill($attrs)->save();
            $project->recordActivity('status', 'Status Changed To '.$project->status_label);
        });

        return new ProjectResource($project->load(['customer', 'assignee']));
    }

    private function validated(Request $request, ?Project $project = null): array
    {
        $required = $project ? 'sometimes' : 'required';

        return $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => [$required, 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => [$required, Rule::in(Project::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
        ]);
    }

    private function perPage(Request $request): int
    {
        return min(
            max(1, (int) $request->query('per_page', config('api.per_page', 25))),
            (int) config('api.max_per_page', 100)
        );
    }
}
