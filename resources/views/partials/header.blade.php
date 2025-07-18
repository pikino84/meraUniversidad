<!-- [ Header ] start -->
<nav class="navbar header-navbar pcoded-header">
    <div class="navbar-wrapper">
        <div class="navbar-logo">
            <a class=" text-left " href="{{ url('/dashboard') }}">
                <img class="img-fluid h-14" src="{{ asset('images/logo_university.svg') }}" alt="Theme-Logo" />
            </a>
            <a class="mobile-menu" id="mobile-collapse" href="#!">
                <i class="feather icon-menu icon-toggle-right"></i>
            </a>
            <a class="mobile-options waves-effect waves-light">
                <i class="feather icon-more-horizontal"></i>
            </a>
        </div>
        <div class="navbar-container container-fluid">
            <ul class="nav-left">
                {{-- 
                <li class="header-search">
                    <div class="main-search morphsearch-search">
                        <div class="input-group">
                            <span class="input-group-text search-close">
                            <i class="feather icon-x input-group-text"></i>
                        </span>
                            <input type="text" class="form-control" placeholder="Enter Keyword">
                            <span class="input-group-text search-btn">
                            <i class="feather icon-search input-group-text"></i>
                        </span>
                        </div>
                    </div>
                </li>
                 --}}
                <li>
                    <a href="#!" onclick="javascript:toggleFullScreen()" class="waves-effect waves-light">
                    <i class="full-screen feather icon-maximize"></i>
                </a>
                </li>
            </ul>
            <ul class="nav-right">
                {{--  
                <li class="header-notification">
                    <div class="dropdown-primary dropdown">
                        <div class="dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="feather icon-bell"></i>
                            <span class="badge bg-c-red">5</span>
                        </div>
                        <ul class="show-notification notification-view dropdown-menu" data-dropdown-in="fadeIn" data-dropdown-out="fadeOut">
                            <li>
                                <h6>Notifications</h6>
                                <label class="form-label label label-danger">New</label>
                            </li>
                            <li>
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <img class="img-radius" src="{{ asset('images/avatar-4.jpg') }}" alt="Generic placeholder image">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="notification-user">John Doe</h5>
                                        <p class="notification-msg">Lorem ipsum dolor sit amet, consectetuer elit.</p>
                                        <span class="notification-time">30 minutes ago</span>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <img class="img-radius" src="{{ asset('images/avatar-3.jpg') }}" alt="Generic placeholder image">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="notification-user">Joseph William</h5>
                                        <p class="notification-msg">Lorem ipsum dolor sit amet, consectetuer elit.</p>
                                        <span class="notification-time">30 minutes ago</span>
                                    </div>
                                </div>
                            </li>
                            <li>
                                <div class="d-flex">
                                    <div class="flex-shrink-0">
                                        <img class="img-radius" src="{{ asset('images/avatar-4.jpg') }}" alt="Generic placeholder image">
                                    </div>
                                    <div class="flex-grow-1">
                                        <h5 class="notification-user">Sara Soudein</h5>
                                        <p class="notification-msg">Lorem ipsum dolor sit amet, consectetuer elit.</p>
                                        <span class="notification-time">30 minutes ago</span>
                                    </div>
                                </div>
                            </li>
                        </ul>
                    </div>
                </li>
                <li class="header-notification">
                    <div class="dropdown-primary dropdown">
                        <div class="displayChatbox dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="feather icon-message-square"></i>
                            <span class="badge bg-c-green">3</span>
                        </div>
                    </div>
                </li>
                --}}
                <li class="user-profile header-notification">

                    <div class="dropdown-primary dropdown">
                        <div class="dropdown-toggle" data-bs-toggle="dropdown">
                            <img src="{{ asset('images/avatar-blank.jpg') }}" class="img-radius" alt="User-Profile-Image">
                            <span>{{ Auth::user()->name }}</span>
                            
                            <i class="feather icon-chevron-down"></i>
                        </div>
                        <ul class="show-notification profile-notification dropdown-menu" data-dropdown-in="fadeIn" data-dropdown-out="fadeOut">
                            {{-- 
                            <li>
                                <a href="#!">
                                    <i class="feather icon-settings"></i> Settings
                                </a>
                            </li>
                             --}}
                            @auth
                            <li>
                                <a href="{{ route('profile.edit') }}">
                                    <i class="feather icon-user"></i> Profile
                                </a>
                            </li>
                            @endauth
                            {{-- 
                            <li>
                                <a href="email-inbox.html">
                                    <i class="feather icon-mail"></i> My Messages
                                </a>
                            </li>
                            <li>
                                <a href="auth-lock-screen.html">
                                    <i class="feather icon-lock"></i> Lock Screen
                                </a>
                            </li>
                             --}}
                            <li>
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <a href="{{ route('logout') }}"
                                       onclick="event.preventDefault(); this.closest('form').submit();">
                                        <i class="feather icon-log-out"></i> Logout
                                    </a>
                                </form>
                                
                            </li>
                        </ul>
                    </div>
                </li>
            </ul>
        </div>
    </div>
</nav>
<!-- [ chat user list ] start -->
<div id="sidebar" class="users p-chat-user showChat">
    <div class="had-container">
        <div class="p-fixed users-main">
            <div class="user-box">
                <div class="chat-search-box">
                    <a class="back_friendlist">
                        <i class="feather icon-x"></i>
                    </a>
                    <div class="right-icon-control">
                        <div class="input-group input-group-button">
                            <input type="text" id="search-friends" name="footer-email" class="form-control" placeholder="Search Friend">
                            <button class="btn btn-primary waves-effect waves-light" type="button">
                                <i class="feather icon-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="main-friend-list">
                    <div class="media userlist-box waves-effect waves-light" data-id="1" data-status="online" data-username="Josephin Doe">
                        <a class="media-left" href="#!">
                                    <img class="media-object img-radius img-radius" src="{{ asset('images/avatar-3.jpg') }}" alt="Generic placeholder image ">
                                    <div class="live-status bg-success"></div>
                                </a>
                        <div class="media-body">
                            <div class="chat-header">Josephin Doe XXX</div>
                        </div>
                    </div>
                    <div class="media userlist-box waves-effect waves-light" data-id="2" data-status="online" data-username="Lary Doe">
                        <a class="media-left" href="#!">
                                    <img class="media-object img-radius" src="{{ asset('images/avatar-2.jpg') }}" alt="Generic placeholder image">
                                    <div class="live-status bg-success"></div>
                                </a>
                        <div class="media-body">
                            <div class="f-13 chat-header">Lary Doe</div>
                        </div>
                    </div>
                    <div class="media userlist-box waves-effect waves-light" data-id="3" data-status="online" data-username="Alice">
                        <a class="media-left" href="#!">
                                    <img class="media-object img-radius" src="{{ asset('images/avatar-4.jpg') }}" alt="Generic placeholder image">
                                    <div class="live-status bg-success"></div>
                                </a>
                        <div class="media-body">
                            <div class="f-13 chat-header">Alice</div>
                        </div>
                    </div>
                    <div class="media userlist-box waves-effect waves-light" data-id="4" data-status="offline" data-username="Alia">
                        <a class="media-left" href="#!">
                                    <img class="media-object img-radius" src="{{ asset('images/avatar-3.jpg') }}" alt="Generic placeholder image">
                                    <div class="live-status bg-default"></div>
                                </a>
                        <div class="media-body">
                            <div class="f-13 chat-header">Alia<small class="d-block text-muted">10 min ago</small></div>
                        </div>
                    </div>
                    <div class="media userlist-box waves-effect waves-light" data-id="5" data-status="offline" data-username="Suzen">
                        <a class="media-left" href="#!">
                                    <img class="media-object img-radius" src="{{ asset('images/avatar-2.jpg') }}" alt="Generic placeholder image">
                                    <div class="live-status bg-default"></div>
                                </a>
                        <div class="media-body">
                            <div class="f-13 chat-header">Suzen<small class="d-block text-muted">15 min ago</small></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- [ chat user list ] end -->

<!-- [ chat message ] start -->
<div class="showChat_inner">
    <div class="d-flex chat-inner-header">
        <a class="back_chatBox">
            <i class="feather icon-x"></i> Josephin Doe YYYYY
        </a>
    </div>
    <div class="main-friend-chat">
        <div class="d-flex chat-messages">
            <a class="media-left photo-table" href="#!">
                <div class="flex-shrink-0">
                    <img class="media-object img-radius img-radius m-t-5" src="{{ asset('images/avatar-2.jpg') }}" alt="Generic placeholder image">
                </div>
            </a>
            <div class="flex-grow-1 chat-menu-content">
                <div class="">
                    <p class="chat-cont">I'm just looking around. Will you tell me something about yourself?</p>
                </div>
                <p class="chat-time">8:20 a.m.</p>
            </div>
        </div>
        <div class="d-flex chat-messages">
            <div class="flex-grow-1 chat-menu-reply">
                <div class="">
                    <p class="chat-cont">Ohh! very nice</p>
                </div>
                <p class="chat-time">8:22 a.m.</p>
            </div>
        </div>
        <div class="d-flex chat-messages">
            <a class="media-left photo-table" href="#!">
                <div class="flex-shrink-0">
                    <img class="media-object img-radius img-radius m-t-5" src="{{ asset('images/avatar-2.jpg') }}" alt="Generic placeholder image">
                </div>
            </a>
            <div class="media-body chat-menu-content">
                <div class="">
                    <p class="chat-cont">can you come with me?</p>
                </div>
                <p class="chat-time">8:20 a.m.</p>
            </div>
        </div>
    </div>
    <div class="chat-reply-box">
        <div class="right-icon-control">
            <div class="input-group input-group-button">
                <input type="text" class="form-control" placeholder="Write hear . . ">
                <button class="btn btn-primary waves-effect waves-light" type="button">
                    <i class="feather icon-message-circle"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<!-- [ chat message ] end -->


