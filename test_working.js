var apiship = apiship || (function () {

    var ID_MODAL = 'apiship_yandex_map';
    var YANDEX_MAP_CONTAINER_ID = 'apiship_yandex_map_container';


    var modal = {
        initLayout: {
            createRoot: function () {
                let root = document.createElement('div')
                root.classList.add('modal', 'fade', 'show')
                root.setAttribute('tabindex', '-1')
                root.setAttribute('role', 'dialog')
                root.setAttribute('id', ID_MODAL)
                document.body.appendChild(root)
                return root;
            },
            createDialog: function (dom) {
                let dialog = document.createElement('div')
                dialog.classList.add('modal-dialog','modal-dialog-centered','wide')
                dom.appendChild(dialog)
                return dialog;
            },
            createContent: function (dom) {
                let content = document.createElement('div')
                content.classList.add('modal-content','apiship_modal-content')
                dom.appendChild(content)
                return content;
            },
            createHeader: function (dom) {
                let header = document.createElement('div')
                header.classList.add('modal-header', 'p-4')
                header.innerHTML = '<h5 class="modal-title fsz-20 d-flex align-items-center justify-content-between" id="loginModalLabel">Пункты самовывоза</h5>'
                dom.appendChild(header)
                return header;
            },
            createButtonClose: function (dom) {
                let buttonClose = document.createElement('button')
                buttonClose.setAttribute('type', 'button')
                buttonClose.setAttribute('data-bs-dismiss', 'modal', 'apiship_modal')
                buttonClose.setAttribute('aria-label', 'Close')
                buttonClose.classList.add('btn-close')
                dom.appendChild(buttonClose)
                return buttonClose;
            },
            createIconClose: function (dom) {
                let iconClose = document.createElement('span')
                iconClose.setAttribute('aria-hidden', 'true')
                iconClose.innerHTML = '&times;'
                dom.appendChild(iconClose)
                return iconClose;
            },
            createBody: function (dom) {
                let body = document.createElement('div')
                body.classList.add('modal-body','position-relative','p-0')
                dom.appendChild(body)
                return body;
            },

        },
        createModalBootstrap: function () {
            if (this.checkOnInit()) return;
            let root = this.initLayout.createRoot(),
                dialog = this.initLayout.createDialog(root),
                content = this.initLayout.createContent(dialog),
                header = this.initLayout.createHeader(content),
                buttonClose = this.initLayout.createButtonClose(header),
                iconClose = this.initLayout.createIconClose(buttonClose),
                body = this.initLayout.createBody(content);
        },
        checkOnInit: function () {
            if (document.getElementById(this.idModal)) {
                return true
            }
            return false
        },
        open: function () {
            $('#' + ID_MODAL).modal('show')
        },
        close: function () {
            $('#' + ID_MODAL).modal('hide')
        },
        destroy: function () {
            if (this.checkOnInit()) {
                document.getElementById(this.idModal).remove()
            }
        }
    };



    var yandexMaps = {
        points: [],
        settings: {},
        initApi: function () {
            yandex_api_key = get_yandex_api_key()

            if (yandex_api_key==='')
                script_src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU'
            else
                script_src = 'https://api-maps.yandex.ru/2.1/?lang=ru_RU&apikey=' + yandex_api_key


            if (typeof ymaps !== 'undefined') return;

            let script = document.createElement('script')
            script.setAttribute('src', script_src)
            script.setAttribute('defer', '')
            script.setAttribute('data', 'yandex-map')
            document.head.appendChild(script)
        },
        createContainer: function () {
            let container = document.createElement('div'),
                modalBody = document.getElementById(ID_MODAL).querySelector('.modal-body');
            container.setAttribute('id', YANDEX_MAP_CONTAINER_ID)
            modalBody.appendChild(container)
        },
        initMap: function (event) {
            var apishipSearchControl = new ymaps.control.SearchControl({
                options: {
                    provider: 'yandex#search',
                    noPopup: 'true'
                }
            });

            Mymap = new ymaps.Map(YANDEX_MAP_CONTAINER_ID, {
                center: [yandexMaps.points[0]['lat'], yandexMaps.points[0]['lon']],
                zoom: 10,
                controls: (get_yandex_api_key()==='')?['zoomControl']:['zoomControl','geolocationControl',apishipSearchControl]
            }, {
                suppressMapOpenBlock: true
            })
            yandexMaps.createPlacemarks(yandexMaps.points, Mymap)

        },
        createPlacemarks: function(points, map) {
            // Удаляем старый objectManager если был
            if (window.apishipObjectManager) {
                map.geoObjects.remove(window.apishipObjectManager);
            }

            // Создаем кастомные метки
            points.forEach(function(point) {
                // Создаем SVG-иконку (можно заменить на свою)
                var svgIcon = `
                    <svg width="40" height="40" viewBox="0 0 40 40" xmlns="http://www.w3.org/2000/svg">
                        <circle cx="20" cy="20" r="18" fill="#4CAF50" stroke="#FFF" stroke-width="2"/>
                        <text x="20" y="26" font-family="Arial" font-size="14" fill="white" text-anchor="middle">${point.text}</text>
                    </svg>`;

                // Создаем метку
                var placemark = new ymaps.Placemark(
                    [point.lat, point.lon],
                    {
                        balloonContentHeader: point.title,
                        balloonContentBody: `Стоимость: ${point.text}`,
                        balloonContentFooter: `<button class="apiship-select-btn" data-id="${point.code}">Выбрать</button>`,
                        hintContent: point.title
                    },
                    {
                        iconLayout: 'default#imageWithContent',
                        iconImageHref: 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svgIcon))),
                        iconImageSize: [40, 40],
                        iconImageOffset: [-20, -40],
                        hideIconOnBalloonOpen: false
                    }
                );

                // Вешаем обработчик клика
                placemark.events.add('click', function(e) {
                    e.preventDefault();
                    callback_function(point.code, callback_code);
                    modal.close();
                });

                map.geoObjects.add(placemark);
            });

            // Обработчик для кнопки в балуне (на всякий случай)
            $(document).off('click', '.apiship-select-btn').on('click', '.apiship-select-btn', function() {
                callback_function($(this).data('id'), callback_code);
                modal.close();
            });
        },
        destroyMap: function () {
            var el = document.getElementById(YANDEX_MAP_CONTAINER_ID);
            if (el !== null) el.remove();
        }
    };

    function onCloseModal() {
        yandexMaps.destroyMap()
    };

    var callback_function = null
    var callback_code = null
    var Mymap = null

    var instance;

    function init() {
        console.log('apiship init...')
        modal.createModalBootstrap()
        yandexMaps.initApi()
        $('#' + ID_MODAL).on('hide.bs.modal', onCloseModal);
        return this;
    };

    return {

        getInstance: function () {

            if (!instance) {
                instance = init();
            }

            return instance;
        },

        open: function (callback, points, code) {

            $('#' + ID_MODAL).remove();
            init();


            callback_function = callback
            callback_code = code

            yandexMaps.createContainer()

            yandexMaps.points = points
            ymaps.ready(yandexMaps.initMap)
            modal.open()

        }


    };


})();

apiship.getInstance()

function apishipInsertImage(el, i, el2) {
    document.querySelectorAll(el)[i].insertAdjacentHTML('afterend', el2)
}

function apishipInsertLink(el_select, el_change) {
    $('input:not([type=hidden])[value^="apiship"][value*="point"][value*="error"]').parent().append(el_select);
    $('input:not([type=hidden])[value^="apiship"][value*="point"]:not([value*="error"])').parent().append(el_change);
}

function apishipGetCode(el) {
    return el.siblings("input").val()
}

function apishipInsertLoader(code) {
    $('input:not([type=hidden])[value="' + code + '"]').parent().append('<div class="apiship_loading"></div>');
}
