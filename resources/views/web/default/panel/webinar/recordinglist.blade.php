@extends(getTemplate() .'.panel.layouts.panel_layout')

@push('styles_top')
<link rel="stylesheet" href="/assets/default/vendors/daterangepicker/daterangepicker.min.css">
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
@endpush

     @section('content')
        <a href="{{url('panel/meetings/manage_meeting')}}" class="btn btn-primary">Back</a>
        @if(!empty($session_data))
            @if($session_data->session_api=="big_blue_button")
               
                <a class="btn btn-dark"  href="https://aimpariksha.com/panel/sessions/{{$session_data->id}}/joinToBigBlueButton">Join</a>
                @elseif($session_data->session_api=="zoom")
                
                 <a class="btn btn-dark" href="{{$session_data->link}}">Join</a>
                  @elseif($session_data->session_api=="local")
                 <a class="btn btn-dark" href="{{$session_data->link}}">Join</a>
                    @elseif($session_data->session_api=="agora")
                    
                 <a class="btn btn-dark" href="https://aimpariksha.com/panel/sessions/{{$session_data->id}}/joinToAgora">Join</a>
                 @endif
                   @endif
                 
                 
            <div class="row">
                @if(count($recording_list)>0)
                
                @foreach($recording_list as $all_recording_data)
                
                
                <div class="card ml-2 mt-2" style="width: 18rem;">
               <div class="card-body">
                <h5 class="card-title">Start Date : {{ gmdate("Y-m-d h:i:sa", $all_recording_data->startTime/1000)}}</h5>
                <h6 class="card-subtitle mb-2 text-muted">End Date: {{   gmdate("Y-m-d h:i:sa", $all_recording_data->endTime/1000)}}</h6>
                <p class="card-title">Title: {{$all_recording_data->title}}</p>
               
                  <a href="{{$all_recording_data->url}}" class="card-link" >View recording</a>
                 <a  class="card-link edit_title" title="{{$all_recording_data->title}}" id_data="{{$all_recording_data->id}}" data-toggle="modal" data-target="#edit_title">
                  Edit
                </a>
              </div>
            </div>
                
                
                
            
          
            @endforeach
            @else
             <h5>No data found</h5>
            @endif
               <!-- Modal -->
        <!-- Modal -->
            <div class="modal fade" id="edit_title" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
              <div class="modal-dialog" role="document">
                <div class="modal-content">
                  <div class="modal-header">
                    <h5 class="modal-title" id="exampleModalLabel">Edit title</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                      <span aria-hidden="true">&times;</span>
                    </button>
                  </div>
                  <div class="modal-body">
                 <form  method="post" action="{{url('panel/meetings/update_title')}}">
                     @csrf
                  <div class="form-group">
                    <label for="recipient-name" class="col-form-label">Title</label>
                    <input type="text" class="form-control" name="title"  id="title_edit">
                  </div>
                   <input type="text" class="form-control" name="id" hidden  id="id_edit">
                 
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-primary">Save</button>
                  </div>
               </form>
                  </div>
                
                </div>
              </div>
            </div>
                        
          </div>
     <script>
         $(document).ready(function(){
            $(document).on('click','.edit_title',function(){
              
                $("#title_edit").val($(this).attr('title'));
                 $("#id_edit").val($(this).attr('id_data'));
            });
             
         })
     </script>

@endsection