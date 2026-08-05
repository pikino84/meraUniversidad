
@extends('layouts.app')

@section('title', 'Perfil de Usuario')

@section('content')

<div class="card mera-form-card">


    <div class="card-header mera-form-header">

        <div class="mera-header-icon">

            <i class="fa fa-user"></i>

        </div>


        <div>

            <h5>Perfil de Usuario</h5>

            <span>
                Administra tu información personal, contraseña y cuenta.
            </span>

        </div>

    </div>



    <div class="card-block">


        <div class="row">


            <div class="col-md-12">


                <div class="card border-1 shadow-sm mb-4">

                    <div class="card-body">

                        @include('profile.partials.update-profile-information-form')

                    </div>

                </div>


            </div>



            <div class="col-md-12">


                <div class="card border-1 shadow-sm mb-4">

                    <div class="card-body">

                        @include('profile.partials.update-password-form')

                    </div>

                </div>


            </div>



            <div class="col-md-12">


                <div class="card border-1 shadow-sm mb-4">

                    <div class="card-body">

                        @include('profile.partials.delete-user-form')

                    </div>

                </div>


            </div>


        </div>


    </div>


</div>


@endsection
