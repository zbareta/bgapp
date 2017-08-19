<!DOCTYPE html>
<html>
<head>
	<title>
		@section('title')
		IDNA - BG APP
		@show
	</title>
	<meta name="token" content="{{ Session::token() }}">

	<link rel="stylesheet" href="{{ URL::asset('/kickstart/css/kickstart.css') }}">

	@yield('styles')

	<script type="text/javascript" src="{{ URL::asset('jquery.min.js') }}"></script>
	<script type="text/javascript" src="{{ URL::asset('/kickstart/js/kickstart.js') }}"></script>

	@yield('scripts')
</head>
<body>

	<ul class="menu">
		<li><a href="/boardgames">Boardgames</a></li>
		<li><a href="/expansions">Expansions</a></li>
		<li><a href="/users">Users</a>
			<ul>
				<li><a href="/user/view_me">My profile</a></li>
			</ul>
		</li>
		<li class="right"><a href="/auth/logout">Logout</a></li>
	</ul>

	<div id="content">
		@yield('content')
	</div>
</body>
</html>
