@extends(getTemplate() .'.panel.layouts.panel_layout')

@push('styles_top')
<link rel="stylesheet" href="/assets/default/vendors/daterangepicker/daterangepicker.min.css">
@endpush

@section('content')


<div class="panel-section-card py-20 px-25 mt-20">
                <div class="row">
                    <form class="form-inline" method="GET">
                      <div class="form-group mb-2">
                        <label for="filter" class="col-sm-2 col-form-label">Filter</label>
                          <?php  
                        
                        
                        $webinar_data_obj = DB::table('webinars')->where('teacher_id', Auth::user()->id)->get();
                        
                        ?>
                        
                         <select name="">
                                  <option value="">
                                  Please select course
                                </option>
                                @foreach($webinar_data_obj as $webinar_obj)
                                <option value="{{$webinar_obj->id}}">
                                    {{$webinar_obj->slug}}
                                </option>
                                @endforeach
                            </select>
                      </div>
                      <button type="submit" class="btn btn-default mb-2">Filter</button>
                    </form>
                    
                    <div class="col-12 ">
                        <div class="table-responsive">
                            <table class="table text-center custom-table">
                                <thead>
                                <tr>
                                      <th class="text-left">Title</th>
                                    <th class="text-left">Course</th>
                                    
                                 
                                    <th class="text-center">Date</th>
                                    
                                    <th class="text-center">Duration</th>
                                    
                                    <th class="text-center">Status</th>
                                  
                                    <th></th>
                                </tr>
                                </thead>
                                <tbody>
                                    @if(Auth::user()->role_id==1)
                                 
                                      @foreach($webinar_data as $webinar_obj)
                                      @if(isset($webinar_obj->webinar))
                                      @foreach($webinar_obj->webinar->sessions as $webinar_obj_data)
                                     
                                                                    <tr>
                                        <td class="text-left">
                                            {{$webinar_obj->webinar['slug']}}
                                          
                                          
                                             
                                        </td>
                                        <td> {{$webinar_obj_data->meeting_title['title']}}</td>
                                        <td class="text-center align-middle">{{    gmdate("Y-m-d\TH:i:s\Z", $webinar_obj->webinar['start_date'])}}</td>
                                        <td class="text-center align-middle">{{ $webinar_obj_data->duration}}</td>
                                      
                                    

                                       

                                      
                                        <td class="text-center align-middle">
                                            <!--<div class="btn-group dropdown table-actions">-->
                                            <!--    <button type="button" class="btn-transparent dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">-->
                                            <!--        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-more-vertical"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>-->
                                            <!--    </button>-->
                                            <!--    <div class="dropdown-menu font-weight-normal">-->
                                                  
                                                   
                                                    
                                                      @if($webinar_obj_data->session_api=="big_blue_button")
                                           
                                                   <a  href="https://aimpariksha.com/panel/sessions/{{$webinar_obj_data->id}}/joinToBigBlueButton" class="webinar-actions d-block mt-10">{{$webinar_obj_data->status=="active"?'Open metting':""}}</a>
                                                    @elseif($webinar_obj_data->session_api=="zoom")
                                                    
                                                    <a   href="{{$webinar_obj_data->link}}" class="webinar-actions d-block mt-10">{{$webinar_obj_data->status=="active"?'Open metting':""}}</a>
                                                      @elseif($webinar_obj_data->session_api=="local")
                                                    <a   href="{{$webinar_obj_data->link}}" class="webinar-actions d-block mt-10">{{$webinar_obj_data->status=="active"?'Open metting':""}}</a>
                                                        @elseif($webinar_obj_data->session_api=="agora")
                                                        
                                                    <a   href="https://aimpariksha.com/panel/sessions/{{$webinar_obj_data->id}}/joinToAgora" class="webinar-actions d-block mt-10">{{$webinar_obj_data->status=="active"?'Open metting':""}}</a>
                                                  
                                                     @endif
                                                    <a href="{{url('panel/meetings/getrecordinglist/'.$webinar_obj_data->id)}}"  class="webinar-actions d-block mt-10 ">Recordings</a>
                                            <!--    </div>-->
                                            <!--</div>-->
                                        </td>
                                    </tr>
                                  
                                    @endforeach
                                  @endif
                                                                  @endforeach
                                                                      {{ $webinar_data->links() }}
                                    @else
                                      @foreach($webinar_data as $webinar_obj)
                                                
                                                <tr>
                                        <td class="text-left">
                                            {{$webinar_obj->webinar['slug']}}
                                          
                                          
                                             
                                        </td>
                                        <td> {{$webinar_obj->meeting_title['title']}}</td>
                                        <td class="text-center align-middle">{{    gmdate("Y-m-d\TH:i:s\Z", $webinar_obj->webinar['start_date'])}}</td>
                                        <td class="text-center align-middle">{{ $webinar_obj->duration}}</td>
                  
                

                   

                  
                    <td class="text-center align-middle">
                        <div class="btn-group dropdown table-actions">
                            <button type="button" class="btn-transparent dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="feather feather-more-vertical"><circle cx="12" cy="12" r="1"></circle><circle cx="12" cy="5" r="1"></circle><circle cx="12" cy="19" r="1"></circle></svg>
                            </button>
                            <div class="dropdown-menu font-weight-normal">
                              
                                
                                
                                  @if($webinar_obj->session_api=="big_blue_button")
                       
                               <a  href="https://aimpariksha.com/panel/sessions/{{$webinar_obj->id}}/joinToBigBlueButton" class="webinar-actions d-block mt-10">Join</a>
                                @elseif($webinar_obj->session_api=="zoom")
                                
                                <a   href="{{$webinar_obj->link}}" class="webinar-actions d-block mt-10">Join</a>
                                  @elseif($webinar_obj->session_api=="local")
                                <a   href="{{$webinar_obj->link}}" class="webinar-actions d-block mt-10">Join</a>
                                    @elseif($webinar_obj->session_api=="agora")
                                    
                                <a   href="https://aimpariksha.com/panel/sessions/{{$webinar_obj->id}}/joinToAgora" class="webinar-actions d-block mt-10">Join</a>
                                 @endif
                                
                                <a href="{{url('panel/meetings/getrecordinglist/'.$webinar_obj->id)}}"  class="webinar-actions d-block mt-10 ">Recordings</a>
                            </div>
                        </div>
                    </td>
                </tr>
                                              @endforeach
                                               {{ $webinar_data->links() }}
                                    @endif

                                              
                                                               
                                                                 
                                                                </tbody>
                            </table>
                            
                        </div>
                    </div>
                </div>
            </div>
@endsection
