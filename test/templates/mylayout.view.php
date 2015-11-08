<html>
	<b>Layout</b>
	{% import file="mymacro" as="utils" %}
	{{ $utils->ie8(); }}
	<div class="content">
		<!-- {% #vacancy %} -->
	</div>

	{% yield name="footer" %}
</html>