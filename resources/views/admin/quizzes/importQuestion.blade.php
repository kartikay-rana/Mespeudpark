@extends('admin.layouts.app')

@push('libraries_top')

@endpush

@section('content')
    <section class="section">
       <div class="row">
        <div class="col-6">
            <div class="card mt-5">
                <div class="card-body">
                    <form action="{{route('import.post')}}" method="post" enctype="multipart/form-data">
                        @csrf
                        <div class="mb-3">
                            <label for="">Quiz question Import</label>
                            <input type="file" name="file" id="" class="form-control">
                            <input type="hidden" name="quiz_id" value="{{$quizeId}}" id="" class="form-control">
                        </div>
                        <div class="mb-3">
                            <input type="submit" class="btn btn-success w-100">
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    </section>
@endsection

@push('scripts_bottom')

@endpush
