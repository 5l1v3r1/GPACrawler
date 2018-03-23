(function() {
    var angke = {};

    /**
     * [getTpl 渲染模板/doT]
     * @param  {[type]} $parentTarget [父元素]
     * @param  {[type]} $data         [加载的数据]
     * @param  {[type]} $target       [当前要渲染的模板]
     * @return {[type]}               [description]
     */
    angke.getTpl = function($parentTarget, $data, $target) {
        tpl = doT.template($target.text());
        $parentTarget.html('').append(tpl($data));
    };

    /**
     * [getUrl 配置访问地址]
     * @return {[type]} [URL]
     */
    angke.getUrl = function() {
        var URL = '/platform/rest/';
        return URL;
    };

    /**
     * 链式调用所用对象
     * @class AjaxObj
     * @namespace Util
     */
    angke.AjaxObj = function() {
        var obj = {};

        obj.done = function(callback) {
            if (callback) this._done = callback;
            return this;
        };

        obj.fail = function(callback) {
            if (callback) this._fail = callback;
            return this;
        };

        obj.empty = function(callback) {
            if (callback) this._empty = callback;
            return this;
        };

        return obj;
    };

    /**
     * [post description]
     * @param  {[type]} url  [请求]
     * @param  {[type]} data [接口数据]
     * @param  {[type]} type [description]
     * @return {[type]}      [description]
     */
    angke.post = function(url, data, type) {
        type = type || 'json';
        return $.ajax({
            url: url,
            data: data || {},
            dataType: type || 'json',
            type: 'POST'
        });
    };

    /**
     * 公用的post请求
     * @param  {[type]} url请求
     * @return {[type]}
     */
    angke.ajaxPost = function(url, param) {
        var obj = new angke.AjaxObj();

        angke.post(url, param)
            .done(function(data) {
                setTimeout(function() {
                    if (data) {
                        if (data.errcode === '0') {
                            obj._done && obj._done(data);
                        } else {
                            alert(data.errmsg);
                        }
                    } else {
                        obj._done && obj._done(data.errmsg);
                    }
                }, 0);
            })
            .fail(function(data) {
                setTimeout(function() {
                    obj._fail && obj._fail(data.errmsg);
                }, 0);
            });
        return obj;
    };

    /**
     * 获取URL中的参数值(单个)
     * @param  {String} name 参数名
     * @return {String} 参数值，若没有该参数，则返回''
     */
    angke.getQueryString = function(name) {
        var reg = new RegExp("(^|&)" + name + "=([^&]*)(&|$)", "i"),
            r = window.location.search.substr(1).match(reg);
        if (r != null) {
            return decodeURI(r[2]);
        }
        return '';
    };

    /**
     * [getAuth 开启微信端的验证方法]
     * @param  {[type]} userid   [userid]
     * @param  {[type]} publicid [publicid]
     * @return {[type]}          [description]
     */
    angke.getAuth = function(userid, publicid) {
        var myDate = new Date();
        var time = myDate.getTime();
        var xx = time.toString();
        var newstr = xx.substring(0, xx.length - 3);
        var j = parseInt(newstr);

        var md5 = hex_md5("182561f33901491fb66d57ad29a39f0f" + "f0ec4b456d8e4f018169498dd3d5941f" + newstr);

        var PostData = {
            "product": "f0ec4b456d8e4f018169498dd3d5941f",
            "version": "1.0",
            "timestamp": j,
            "token": md5,
            "userid": userid,
            "publicid": publicid
        };
        var json_data = PostData;
        return json_data;
    };

    /**
     * [getParam 获取微信认证参数]
     * @return {[type]} [description]
     */
    angke.getParam = function(id,useId) {
        var userid = angke.getQueryString('userid');
        code = angke.getQueryString('code'),
            state = angke.getQueryString('state'),
            data = {};
        if (id) {
            data = {
                userid: userid || useId,
                code: code,
                state: state,
                xnxq: id
            };
        } else {
            data = {
                userid: userid || useId,
                code: code,
                state: state
            };
        };


        // console.log(auth_code);
        var param = {
            auth: JSON.stringify(angke.getAuth('1', '6c59561668c94ccd88f129f2eff673fc')),
            data: JSON.stringify(data)
        }

        return param;
    };

    angke.Cookie = function(key, value) {
        this.key = key;
        if (value != null) {
            this.value = escape(value);
        }
        this.expiresTime = null;
        this.domain = null;
        this.path = "/";
        this.secure = null;
    }

    angke.Cookie.prototype.setValue = function(value) { 
        this.value = escape(value); 
    }
    angke.Cookie.prototype.getValue = function() {
        return (this.value);
    }

    angke.Cookie.prototype.setExpiresTime = function(time) { 
        this.expiresTime = time; 
    }
    angke.Cookie.prototype.getExpiresTime = function() {
        return this.expiresTime;
    }

    angke.Cookie.prototype.setDomain = function(domain) { 
        this.domain = domain; 
    }
    angke.Cookie.prototype.getDomain = function() {
        return this.domain;
    }

    angke.Cookie.prototype.setPath = function(path) { 
        this.path = path; 
    }
    angke.Cookie.prototype.getPath = function() {
        return this.path;
    }

    angke.Cookie.prototype.Write = function(v) {
        if (v != null) {
            this.setValue(v);
        }
        var ck = this.key + "=" + this.value;
        if (this.expiresTime != null) {
            try {
                ck += ";expires=" + this.expiresTime.toUTCString();;
            } catch (err) {
                alert("expiresTime参数错误");
            }
        }
        if (this.domain != null) {
            ck += ";domain=" + this.domain;
        }
        if (this.path != null) {
            ck += ";path=" + this.path;
        }
        if (this.secure != null) {
            ck += ";secure";
        }
        document.cookie = ck;
    }

    angke.Cookie.prototype.Read = function() {
        try {
            var cks = document.cookie.split("; ");
            var i = 0;
            for (i = 0; i < cks.length; i++) {
                var ck = cks[i];
                var fields = ck.split("=");
                if (fields[0] == this.key) {
                    this.value = fields[1];
                    return (this.value);
                }
            }
            return null;
        } catch (err) {
            alert("cookie读取错误");
            return null;
        }
    }

    window.angke = angke;
})(window)
