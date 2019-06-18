<!-- Main Header -->
<header class="main-header">

  <!-- Logo -->
  <a href="{{url("/register/$source/1")}}" class="logo">
    <!-- mini logo for sidebar mini 50x50 pixels -->
    <span class="logo-mini">
        @if(isset($short))
            {{$short}}
        @else
            {{substr($source, 0,4)}}
        @endif
    </span>
    <!-- logo for regular state and mobile devices -->
    <span class="logo-lg"><b>{{$short}}</b>Registration</span>
  </a>

  <!-- Header Navbar -->
  <nav class="navbar navbar-static-top" role="navigation">
    <!-- Sidebar toggle button-->
    <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
      <span class="sr-only">Toggle navigation</span>
    </a>
    <!-- Navbar Right Menu -->
    <div class="navbar-custom-menu">
      <ul class="nav navbar-nav">

        <!-- Control Sidebar Toggle Button -->

      </ul>
    </div>
  </nav>
</header>
