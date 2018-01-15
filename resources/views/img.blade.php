<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>输入验证码</title>

</head>
<body>
<img src="{{$randCode->path}}" alt="" id="image">
<br>
<label for="" id="value">当前答案: {{$randCode->value}}</label>
@if($randCode->is_ok == 0)
    <p style="color: red">未验证</p>
@else
    <p style="color: green">已验证</p>
@endif
<br>
<form action="" method="post">
    {{csrf_field()}}
    <label for="answer" id="label">请输入答案</label>
    <input type="text" id="answer" name="answer" value="" required>
    <button type="button" onclick="$('#answer').val('')">清除</button>
    <button type="submit">提交答案</button>
</form>

<a href="{{URL::to('/image/'.($id + 1))}}">下一张</a>
<script src="https://cdn.bootcss.com/jquery/1.12.4/jquery.min.js"></script>

<script>
    var xSpan = 0;
    var ySpan = 0;
    window.onload = function () {
        var imgNode = document.getElementById("image");

        imgNode.onmousemove = function () {
            xSpan = event.clientX;
            ySpan = event.clientY;
        }
    };
    $('#image').on('click', function () {

        var text = $('#answer').val();
        if (text == '') {

            $('#answer').val(xSpan + "," + (ySpan-30))
        } else {
            $('#answer').val(text + "," + xSpan + "," + (ySpan-30))
        }
    })
</script>
</body>
</html>
