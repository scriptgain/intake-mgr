<x-layouts.app title="New Project">
    <x-page-header
        eyebrow="Service Desk"
        title="New Project"
        icon="folder"
        subtitle="Group related tickets and work orders under one engagement."
        :back="['href' => route('projects.index'), 'label' => 'All Projects']" />

    @include('admin.projects._form')
</x-layouts.app>
