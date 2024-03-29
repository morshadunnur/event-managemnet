@extends('front-end.layouts.master')

@section('content')
    
    
    <!--Page Title-->
    <section class="page-title" style="background-image:url('{{ asset('public/frontend/images/background/12.jpg') }}');">
        <div class="container">
            <div class="title-text">
                <h1>Executive Committee</h1>
                <ul class="title-menu clearfix">
                    <li><a href="URL::to('/')">home</a></li>
                    <li>Executive Committee</li>
                </ul>
            </div>           
        </div>
    </section>
    <!--End Page Title-->


    <!--single-speaker Style-->
    <section class="news-section" style="background-image: url('{{ asset('public/frontend/images/background/8.jpg') }}');">
        <div class="container">
        <div class="section-title text-center">
                <h3>Executive<span> Committee</span></h3>
                <p></p>
            </div>
            <div class="row">
                <div class="col-md-12 col-sm-12 col-xs-12">
                    <div class="image-box embed-responsive embed-responsive-16by9">
                        <figure>
                        <!-- <embed class="embed-responsive-item" src="{{ asset('public/uploads/event-brochure-pdf/'.$activeEvent->event_brochure_pdf) }}" width="100%" height="200px"> -->
                        <embed class="embed-responsive-item" src="http://docs.google.com/viewer?url={{ asset('public/uploads/executive-committe/New-EC-2017-2018.pdf') }}&embedded=true" width="100%" height="200px"> 
                        </figure>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <!--single-speaker Style-->

@stop


