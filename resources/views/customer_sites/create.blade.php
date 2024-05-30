@extends('layouts.app')

@section('title', __('customer_site.create'))

@section('content')
<div class="row justify-content-center">
    <div class="col-md-6">
        <div class="card">
            <div class="card-header">{{ __('Create Resource Monitor') }}</div>
            {{ Form::open(['route' => 'customer_sites.store']) }}
            <div class="card-body">
                <div class="row">
                    <div class="col-md-8">
                        {!! FormField::text('name', ['required' => true, 'label' => __('customer_site.name'), 'placeholder' => 'Example Web']) !!}
                    </div>
                    <div class="col-md-4">
                        {!! FormField::select('vendor_id', $availableVendors, ['label' => __('Group'), 'id' => 'vendor-select']) !!}
                    </div>
                </div>
                {!! FormField::text('url', ['label' => __('customer_site.url'), 'placeholder' => 'https://example.net']) !!}
                
                <!-- Username and Password Fields -->
                <div class="row mt-3" id="credentials-fields" style="display: none;">
                    {{-- <div class="col-md-8">
                        {!! FormField::text('port', ['label' => __('port'), 'placeholder' => 'port']) !!}
                    </div> --}}
                    <div class="col-md-4">
                        {!! FormField::text('topic', ['label' => __('topic'), 'placeholder' => 'topic']) !!}
                    </div>
                    {{-- <div class="col-md-8">
                        {!! FormField::text('username', ['label' => __('Username'), 'placeholder' => 'Username']) !!}
                    </div>
                    <div class="col-md-4">
                        {!! FormField::password('password', ['label' => __('Password'), 'placeholder' => 'Password']) !!}
                    </div> --}}
                </div>
            </div>
            <div class="card-footer">
                {{ Form::submit(__('app.create'), ['class' => 'btn btn-success']) }}
                {{ link_to_route('customer_sites.index', __('app.cancel'), [], ['class' => 'btn btn-link']) }}
            </div>
            {{ Form::close() }}
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const vendorSelect = document.getElementById('vendor-select');
        const credentialsFields = document.getElementById('credentials-fields');

        vendorSelect.addEventListener('change', function () {
            if (vendorSelect.options[vendorSelect.selectedIndex].text === 'Nodes') { // Change 'Nodes' to the actual text for the Node vendor if different
                credentialsFields.style.display = 'block';
            } else {
                credentialsFields.style.display = 'none';
            }
        });
    });
</script>
@endsection
