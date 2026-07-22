<x-layouts.app :title="'Edit ' . $project->number">
    <x-page-header
        eyebrow="Project"
        :title="'Edit ' . $project->number"
        icon="folder"
        :subtitle="$project->name"
        :back="['href' => route('projects.show', $project), 'label' => 'Back To Project']" />

    @include('admin.projects._form')
</x-layouts.app>
