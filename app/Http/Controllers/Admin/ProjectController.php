<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * An engagement grouping related tickets and work orders. Full CRUD plus a
 * status control; progress is derived from the completion of its work orders.
 */
class ProjectController extends Controller
{
    public function index(Request $request)
    {
        $projects = Project::with(['customer', 'assignee'])
            ->withCount(['tickets', 'workOrders'])
            ->search($request->string('q')->toString() ?: null)
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->latest()
            ->paginate((int) config('shop.rows_per_page', 25))
            ->withQueryString();

        $filters = $request->only(['q', 'status']);

        return view('admin.projects.index', [
            'projects' => $projects,
            'filters' => $filters,
            'tabs' => $this->indexTabs($filters),
        ]);
    }

    private function indexTabs(array $filters): array
    {
        $counts = [
            'all' => Project::count(),
            'planning' => Project::where('status', 'planning')->count(),
            'active' => Project::where('status', 'active')->count(),
            'on_hold' => Project::where('status', 'on_hold')->count(),
            'completed' => Project::where('status', 'completed')->count(),
            'cancelled' => Project::where('status', 'cancelled')->count(),
        ];

        $definitions = [
            ['key' => 'all', 'label' => 'All', 'status' => null],
            ['key' => 'planning', 'label' => 'Planning', 'status' => 'planning'],
            ['key' => 'active', 'label' => 'Active', 'status' => 'active'],
            ['key' => 'on_hold', 'label' => 'On Hold', 'status' => 'on_hold'],
            ['key' => 'completed', 'label' => 'Completed', 'status' => 'completed'],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'status' => 'cancelled'],
        ];

        $active = $filters['status'] ?? null;
        $search = array_filter(['q' => $filters['q'] ?? null]);

        return array_map(fn ($tab) => [
            'label' => $tab['label'],
            'count' => $counts[$tab['key']] ?? 0,
            'active' => $tab['status'] === $active,
            'href' => route('projects.index', array_merge(
                $tab['status'] ? ['status' => $tab['status']] : [],
                $search
            )),
        ], $definitions);
    }

    public function create()
    {
        return view('admin.projects.create', [
            'project' => new Project(['status' => 'planning']),
            'customers' => Customer::orderBy('first_name')->orderBy('last_name')->get(),
            'agents' => User::orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validateProject($request);

        $project = DB::transaction(function () use ($data) {
            $project = Project::create($data);
            $project->recordActivity('created', 'Project Created');

            return $project;
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project '.$project->number.' created.');
    }

    public function show(Project $project)
    {
        $project->load([
            'customer', 'assignee',
            'tickets.assignee', 'workOrders.assignee',
            'activities.user',
        ]);

        return view('admin.projects.show', [
            'project' => $project,
        ]);
    }

    public function edit(Project $project)
    {
        return view('admin.projects.edit', [
            'project' => $project,
            'customers' => Customer::orderBy('first_name')->orderBy('last_name')->get(),
            'agents' => User::orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, Project $project)
    {
        $data = $this->validateProject($request);

        DB::transaction(function () use ($project, $data) {
            $project->forceFill($data)->save();
            $project->recordActivity('note', 'Project Updated');
        });

        return redirect()
            ->route('projects.show', $project)
            ->with('status', 'Project updated.');
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

        return back()->with('status', 'Project status updated.');
    }

    public function destroy(Project $project)
    {
        $project->delete();

        return redirect()->route('projects.index')->with('status', 'Project deleted.');
    }

    public function bulkDestroy(Request $request)
    {
        $ids = collect($request->input('ids', []))->filter()->all();
        $count = Project::whereIn('id', $ids)->delete();

        return back()->with('status', "Deleted {$count} project(s).");
    }

    private function validateProject(Request $request): array
    {
        return $request->validate([
            'customer_id' => ['nullable', 'integer', 'exists:customers,id'],
            'assigned_user_id' => ['nullable', 'integer', 'exists:users,id'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(Project::STATUSES)],
            'starts_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date'],
        ]);
    }
}
