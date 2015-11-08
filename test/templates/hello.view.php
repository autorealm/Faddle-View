{% extends file="mylayout" as="vacancy" %}
<?php $var=0; ?>
{#
这是一段模板注释。
#}

<div>
<h3>模板实例</h3>
	{{ if $var }}
		{{ $var }}
	{{ else }}
		'same message.'
	{{ endif }}
	<br>
	{{ $engine }}
	
<p><b>消耗时间：</b>{{ elapsed_time var1="x321" var2="v34534" var3="k4332" }}</p>

<?php echo 'this echo by php'; ?>

<?php $v3 = array('nem'=>'Firsot,in co.','cor'=>12500, '', 'empty'=> '') ; ?>

<p>{{ 'FILTER'|lower|capitalize }}</p>
<p>{{ 'filter,by 2-87 @ 中文在此'|truncate:(20,'xx')|upper|is_string|true:$v3|var_dump }}</p>
<p>{{ 'md3ef556uuk45dfgsen14g'|random:32|len:300|eq:(32,'正确','错误') }}</p>
<p>{{ $v3|trims|var_dump }}</p>

{#!--{_SERVER}--#}
{% section name="footer" %}
{{ echo '<hr />'; }}
<p> @2015 autorealm, col. </p>
{% endsection %}

{{ foreach $users ($k, $v) }}
	{{ $v }}
{{ endforeach }}
</div>
{# include file="home" as="home" with={"vacancy":"<a nohref>with-form</a>"} only #}

{% include file="test" as="test" %}

<!-- 以下内容原样显示 -->

{% literal %}
<div>
{{ $engine }}
<br>
<?php echo 'literal<br>'; ?>
{% section name="footer" %}
<p> @2015 autorealm, col. </p>
{% endsection %}
<br>
{% include file="test" as="test" %}
</div>
{% endliteral %}

<!-- 内容原样显示 结束 -->
