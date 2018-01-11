<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>输入验证码</title>
    <link href="https://cdn.bootcss.com/bootstrap/3.3.7/css/bootstrap.min.css" rel="stylesheet">

</head>
<body>
<img src="{{$randCode->path}}" alt="" id="image">
<label for="" id="label"></label>
<button type="button" onclick="$('#label').html('')">清除</button>
<a href="{{URL::to('/image/'.($id + 1))}}">下一张</a>
<script src="https://cdn.bootcss.com/jquery/1.12.4/jquery.min.js"></script>
<script src="https://cdn.bootcss.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>

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

        var text = $('#label').html();
        if (text == '') {

            $('#label').html(xSpan + "," + (ySpan-30))
        } else {
            $('#label').html(text + "," + xSpan + "," + (ySpan-30))
        }
    })
</script>
</body>
</html>
