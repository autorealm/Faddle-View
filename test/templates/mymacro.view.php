{% macro ie8($var="ViewMacro") %}
	<!--[if lt IE 9]>
		<title>{{$var}}</title>
		<script src="html5shiv.js"></script>
		<script src="respond.js"></script>
		{% include file="test" %}
	<![endif]-->
{% endmacro %}