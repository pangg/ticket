<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>输入验证码</title>

</head>
<body>
<div id="div">
    <img src="/code.jpeg" alt="" id="image" v-on:click="imageClick">
    <br>
    <input type="text" v-model="val" value="@{{val}}">
    <button type="button" v-on:click="clearVal">清除</button>
</div>
<br>
<script src="https://cdn.bootcss.com/vue/2.5.13/vue.min.js"></script>
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
    var vue = new Vue({
        el: '#div',
        data: {
            val: ''
        },
        methods: {
            imageClick: function () {
                if (vue.val === '') {

                    vue.val = xSpan + ',' + (ySpan - 30);
                } else {

                    vue.val += ',' + xSpan + ',' + (ySpan - 30);
                }
            },
            clearVal: function () {
                vue.val = '';
            }
        }
    });

</script>
</body>
</html>
