/**
 * Raptor Dashboard - JavaScript Utilities
 *
 * Энэ файл нь Dashboard UI-ийн нийтлэг функцуудыг нэгтгэсэн сан юм.
 *  Доорх функцууд нь:
 *  CSRF + WAF-compatible fetch wrapper (getCsrfToken / wafBodyEncodingEnabled /
 *      b64EncodeUnicode / csrfFetch)
 *  AJAX Modal Loader (ajaxModal)
 *  Sidebar link activation (activateLink)
 *  Top Notification (Notify)
 *  Button Spinner (spinNstop / growNstop)
 *  Scroll-To-Top Button (initScrollToTop)
 *  Global search modal, Ctrl+K (initGlobalSearch)
 *  Sidebar badge system (initSidebarBadges)
 *  Logout confirmation (initLogoutConfirm)
 *  Topbar language/theme dropdowns, dark mode (initTopbarQuick)
 *  Topbar organization switcher (initOrgSwitcher)
 *  Invalid tab focus (initInvalidTabFocus)
 *  Logger Protocol loader (initLoggerProtocol)
 *
 * Raptor Dashboard бүхэн энэ файлыг залгаж ашиглана.
 *
 * Хөгжүүлэгч энэхүү файлыг өөрийн Dashboard-д дахин өргөтгөж
 * өөрийн функцүүдийг ч нэмэх боломжтой.
 *
 * Анхаарах зүйлс:
 *  * Bootstrap modal механизм ашигладаг
 *  * <a data-bs-toggle="modal" data-bs-target="#static-modal">
 *      гэсэн линкүүд дээр AJAX ачаалалт ажиллана
 *  * Inline болон external <script> tag-уудыг response дотороос
 *      автоматаар execution хийнэ
 *  * Notify() нь системийн бүх popup notification-ийг орлодог
 *  * Button-ууд дээр .spinNstop() ашиглахад илүү амар
 */

/**
 * getCsrfToken()
 * - Dashboard meta tag-аас CSRF token уншина */
function getCsrfToken() {
    const meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
}

/**
 * wafBodyEncodingEnabled()
 * - <meta name="waf-body-encoding"> флагийг уншина (raptor_waf_body_encoding).
 *   "1" бол body-г base64-аар кодолж WAF-ийн body-inspection-ийг тойрно. */
function wafBodyEncodingEnabled() {
    const meta = document.querySelector('meta[name="waf-body-encoding"]');
    return !meta || meta.getAttribute('content') !== '0';
}

/**
 * b64EncodeUnicode(str)
 * - utf-8 (Монгол кирилл г.м.)-д аюулгүй base64 encode. btoa() нь Unicode-г
 *   шууд боловсруулдаггүй тул эхлээд utf-8 байт болгоно. Том агуулгыг
 *   chunk-аар боловсруулна (call stack overflow-оос сэргийлж). */
function b64EncodeUnicode(str) {
    const bytes = new TextEncoder().encode(str);
    let bin = '';
    const chunk = 0x8000;
    for (let i = 0; i < bytes.length; i += chunk) {
        bin += String.fromCharCode.apply(null, bytes.subarray(i, i + chunk));
    }
    return btoa(bin);
}

/**
 * csrfFetch(url, options)
 * - fetch() wrapper, CSRF token header автоматаар нэмнэ
 * - PUT/PATCH/DELETE-г post болгож, жинхэнэ method-ийг X-HTTP-Method-Override
 *   header-аар дамжуулна. Зарим shared hosting (cPanel/LiteSpeed/mod_security)
 *   эдгээр verb-ийг server түвшинд 403-аар блоклодог; server тал дахь
 *   MethodOverrideMiddleware override header-аас method-ийг сэргээнэ.
 * - WAF body-encoding идэвхтэй үед FormData body-гийн string талбаруудыг
 *   base64-аар кодолно. mod_security WAF нь body дахь HTML/JS-төстэй агуулгыг
 *   (rich-text content, <a>, <img>, <script>) XSS гэж андуурч 403 буцаадаг;
 *   кодлосноор WAF-д ил харагдахгүй. Server тал BodyEncodingMiddleware
 *   буцааж decode хийнэ. Файл (File/Blob) болон талбарын нэрс хөндөгдөхгүй. */
function csrfFetch(url, options = {}) {
    if (!options.headers) {
        options.headers = {};
    }

    // Verb tunneling: put/patch/delete -> post + override header
    let overrideMethod = null;
    const method = (options.method || 'GET').toUpperCase();
    if (method === 'PUT' || method === 'PATCH' || method === 'DELETE') {
        overrideMethod = method;
        options.method = 'POST';
    }

    // FormData string талбаруудыг base64-аар кодлох (WAF body-inspection-ийг тойрох).
    let bodyEncoded = false;
    if (wafBodyEncodingEnabled() && options.body instanceof FormData) {
        const encoded = new FormData();
        for (const [name, value] of options.body.entries()) {
            if (typeof value === 'string') {
                encoded.append(name, b64EncodeUnicode(value));
                bodyEncoded = true;
            } else {
                encoded.append(name, value);
            }
        }
        if (bodyEncoded) {
            options.body = encoded;
        }
    }

    if (options.headers instanceof Headers) {
        options.headers.append('X-CSRF-TOKEN', getCsrfToken());
        if (overrideMethod) {
            options.headers.append('X-HTTP-Method-Override', overrideMethod);
        }
        if (bodyEncoded) {
            options.headers.append('X-Body-Encoding', 'base64');
        }
    } else {
        options.headers['X-CSRF-TOKEN'] = getCsrfToken();
        if (overrideMethod) {
            options.headers['X-HTTP-Method-Override'] = overrideMethod;
        }
        if (bodyEncoded) {
            options.headers['X-Body-Encoding'] = 'base64';
        }
    }
    return fetch(url, options);
}

/* dark mode идэвхжүүлэх */
if (localStorage.getItem('data-bs-theme') === 'dark') {
    document.body.setAttribute('data-bs-theme', 'dark');
}

/**
 * ajaxModal(link)
 * - Modal-ийн агуулгыг AJAX-аар ачаалж харуулна
 *
 * @description
 *  data-bs-target="#static-modal" гэсэн modal руу HTML response
 *  ачаалж, скриптуудыг сэргээж ажиллуулдаг ухаалаг loader.
 *
 * @param {HTMLElement} link - modal нээж буй <a> эсвэл <button>
 */
function ajaxModal(link)
{
    let url;
    if (link.hasAttribute('href')) {
        url = link.getAttribute('href');
    }
    if (!url || url.startsWith('javascript:;')) {
        return;
    }

    const modalId = link.getAttribute('data-bs-target');
    if (!modalId) return;
    const modalDiv = document.querySelector(modalId);
    if (!modalDiv) return;

    const method = link.getAttribute('method');
    const xhr = new XMLHttpRequest();
    xhr.onreadystatechange = function () {
        if (this.readyState === XMLHttpRequest.DONE) {
            modalDiv.innerHTML = this.responseText;

            /* хуудсан дахь <script> tag-уудыг дотор нь ажиллуулна */
            const parser = new DOMParser();
            const responseDoc = parser.parseFromString(this.responseText, 'text/html');
            responseDoc.querySelectorAll('script').forEach(function (script) {
                if (script.src) {
                    /* External JS - өмнө нь залгагдаагүй бол шинээр залгана
                     * (modal дахин нээгдэх бүрт давхар ачаалахаас сэргийлнэ) */
                    const loaded = Array.from(document.scripts).some(s => s.src === script.src);
                    if (!loaded) {
                        const newScript = document.createElement('script');
                        newScript.src = script.src;
                        document.body.appendChild(newScript);
                    }
                } else if (script.innerHTML.trim() !== '') {
                    /* Inline JS-ийг IIFE-ээр орооод тусдаа scope-д ажиллуулна -
                     * modal-ыг дахин нээхэд top-level const/let давхар зарлагдаж
                     * "Identifier has already been declared" SyntaxError үүсэхээс
                     * сэргийлнэ. Ажилласны дараа script node-ийг DOM-оос цэвэрлэнэ. */
                    const newInlineScript = document.createElement('script');
                    newInlineScript.textContent = '(function () {\n' + script.innerHTML + '\n})();';
                    document.body.appendChild(newInlineScript);
                    newInlineScript.remove();
                }
            });

            /* response error handler */
            if (this.status !== 200) {
                const isModal = responseDoc.querySelector('div.modal-dialog');
                if (!isModal) {
                    modalDiv.innerHTML = `
                       <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-body">
                                    <div class="alert alert-danger shadow-sm mt-3">
                                        <i class="bi bi-bug-fill"></i>
                                        Error [${this.status}]: <strong>${this.statusText}</strong>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                             </div>
                        </div>`;
                }
            }
        }
    };
    xhr.open(method || 'GET', url, true);
    xhr.send();
}

/**
 * activateLink(href)
 * - Sidebar-ийн идэвхтэй линк тодруулах
 * @param {string} href - Document link */
function activateLink(href)
{
    if (!href) return;

    document.querySelectorAll('.sidebar-menu a.nav-link').forEach(function (a) {
        const aLink = a.getAttribute('href');
        if (aLink && href.startsWith(aLink)) {
            a.classList.add('active');
        }
    });
}

/**
 * Notify(type, title, content)
 * - Дэлгэцийн төвөөс гарч ирээд автоматаар алга болох notification
 *
 * @param {string} type - success, danger, warning, primary
 * @param {string} title - гарчиг
 * @param {string} content - доторх текст
 * @param {number} _velocity - (unused, backward compat)
 * @param {number} delay - автоматаар хаагдах хугацаа (ms)
 */
function Notify(type, title, content, _velocity = 5, delay = 2500)
{
    const previous = document.querySelector('.notifyTop');
    if (previous) previous.remove();

    const bgColor = {
        success: '#198754', danger: '#dc3545',
        warning: '#ffc107', primary: '#0d6efd'
    }[type] || '#0dcaf0';
    const textColor = type === 'warning' ? '#000' : '#fff';

    const iconMap = {
        success: 'bi-check-circle-fill',
        danger:  'bi-exclamation-triangle-fill',
        warning: 'bi-exclamation-circle-fill',
        primary: 'bi-info-circle-fill'
    };
    const icon = iconMap[type] || 'bi-info-circle-fill';

    const el = document.createElement('div');
    el.className = 'notifyTop';
    el.style.cssText =
        `position:fixed;top:50%;left:50%;transform:translate(-50%,-50%) scale(0);
         z-index:11500;padding:1.25rem 2rem;border-radius:.75rem;
         background:${bgColor};color:${textColor};
         box-shadow:0 8px 32px rgba(0,0,0,.25);
         text-align:center;max-width:min(420px,90vw);width:max-content;
         opacity:0;transition:transform .3s ease,opacity .3s ease;pointer-events:none`;
    el.innerHTML =
        `<div style="font-size:1.5rem;margin-bottom:.25rem"><i class="bi ${icon}"></i></div>
         <div style="font-weight:600;text-transform:uppercase;margin-bottom:.25rem">${title}</div>
         <div style="font-size:.9rem;opacity:.9">${content}</div>`;

    document.body.appendChild(el);

    requestAnimationFrame(() => {
        el.style.transform = 'translate(-50%,-50%) scale(1)';
        el.style.opacity = '1';
    });

    setTimeout(() => {
        el.style.transform = 'translate(-50%,-50%) scale(0)';
        el.style.opacity = '0';
        el.addEventListener('transitionend', () => el.remove(), { once: true });
    }, delay);
}

/**
 * Button Spinner - spinNstop(), growNstop()
 *
 * @description
 *  Button дээр loader spinner тавиад, disable болгох.
 *  Ajax дуусаад буцааж сэргээхэд ашиглана.
 * @param {HTMLElement} ele - Button element
 * @param {string} type - spinner төрөл (border эсвэл grow)
 * @param {bool} block - element-ийн дотоод агуулгыг блоклох эсэх
 */
function spinStop(ele, type, block)
{
    const isDisabled = ele.disabled;
    const hasDisabled = ele.classList.contains('disabled');
    const attrText = ele.getAttribute('data-innerHTML');
    if (isDisabled && hasDisabled && attrText) {
        ele.disabled = false;
        ele.classList.remove('disabled');
        ele.innerHTML = attrText;
        return;
    }

    const html = ele.innerHTML;
    ele.setAttribute('data-innerHTML', html);
    const lgStyle = ele.classList.contains('btn-lg') ? ' style="position:relative;top:-2px"' : '';
    let spanHtml = `<span class="spinner-${type} spinner-${type}-sm" role="status"${lgStyle}></span>`;
    if (!block) spanHtml += ' ' + html;

    ele.innerHTML = spanHtml;
    ele.disabled = true;
    ele.classList.add('disabled');
}
Element.prototype.spinNstop = function (block = true) {
    spinStop(this, 'border', block);
};
Element.prototype.growNstop = function (block = true) {
    spinStop(this, 'grow', block);
};

/* Scroll-To-Top Button */
function initScrollToTop(options = {}) {
    /* Default options */
    const config = {
        right: options.right ?? '25%',
        bottom: options.bottom ?? '0px',
        bgColor: options.bgColor ?? '#7952b3',
        hoverColor: options.hoverColor ?? 'blue',
        sizeW: options.sizeW ?? '40px',
        sizeH: options.sizeH ?? '25px',
        threshold: options.threshold ?? 200
    };

    /* Avoid creating multiple buttons */
    if (document.getElementById('scrollToTopBtn')) return;

    /* Create arrow icon */
    const upArrow = document.createElement('i');
    upArrow.style.cssText =
        'border:solid black;border-width:0 2px 2px 0;border-color:white;display:inline-block;' +
        'padding:3.4px;margin-top:11px;transform:rotate(-135deg);-webkit-transform:rotate(-135deg)';

    /* Create button */
    const btnScroll = document.createElement('a');
    btnScroll.id = 'scrollToTopBtn';
    btnScroll.style.cssText =
        `display:inline-block;cursor:pointer;background-color:${config.bgColor};` +
        `width:${config.sizeW};height:${config.sizeH};text-align:center;` +
        `border-radius:6px 6px 0px 0px;position:fixed;right:${config.right};bottom:${config.bottom};` +
        `transition:background-color .3s, opacity .5s, visibility .5s;opacity:0.75;` +
        `visibility:hidden;z-index:10000`;

    btnScroll.appendChild(upArrow);
    document.body.appendChild(btnScroll);

    /* Scroll detection */
    window.addEventListener('scroll', function () {
        const windowpos = document.documentElement.scrollTop;
        if (windowpos > config.threshold) {
            btnScroll.style.opacity = '0.75';
            btnScroll.style.visibility = 'visible';
        } else {
            btnScroll.style.opacity = '0';
            btnScroll.style.visibility = 'hidden';
        }
    });

    /* Smooth scroll */
    btnScroll.addEventListener('click', function (e) {
        e.preventDefault();
        scroll({ top: 0, behavior: 'smooth' });
    });

    /* Hover states */
    btnScroll.addEventListener('mouseover', () => {
        btnScroll.style.backgroundColor = config.hoverColor;
    });
    btnScroll.addEventListener('mouseout', () => {
        btnScroll.style.backgroundColor = config.bgColor;
    });
}

/**
 * initGlobalSearch(config)
 * - Ctrl+K (Mac дээр Cmd+K) глобал хайлтын modal.
 *   Topbar-ийн хайх товч (#global-search-trigger) эсвэл Ctrl+K -> modal нээгдэнэ.
 *
 * config:
 *   searchUrl - хайлтын GET endpoint (жишээ: /dashboard/search)
 *   patterns  - {source: '/dashboard/news/view/{id}', ...} route pattern map
 *               (dashboard.html-ээс |pattern filter-ээр дамжина, hardcode-гүй)
 *
 * Route бүртгэгдээгүй app: |link / |pattern filter нь олдоогүй route-д '#'
 * буцаадаг. searchUrl === '#' бол хайлтын route устгагдсан гэсэн үг тул
 * icon-ийг нуугаад юу ч хийхгүй буцна (хоосон хайлт, илүүдэл self-fetch
 * гарахгүй). Pattern нь '#' source-ийн үр дүн жагсаалтаас нуугдана - модуль
 * нь app-д байхгүй тул хоосон линк рүү хөтлөхгүй.
 */
function initGlobalSearch(config) {
    const modalEl = document.getElementById('global-search');
    const trigger = document.getElementById('global-search-trigger');
    const input = document.getElementById('global-search-input');
    const resultsDiv = document.getElementById('global-search-results');
    if (!modalEl || !input || !resultsDiv || !config) return;

    if (!config.searchUrl || config.searchUrl === '#') {
        if (trigger) trigger.remove();
        return;
    }

    const modal = new bootstrap.Modal(modalEl);

    /* modal: true -> tuhain modul' zuvhun modal fragment-eer view hiideg tul
       ur dun deer darahad shuud shiljihgui, static-modal dotor achaalna. */
    const SOURCE_META = {
        news:            { icon: 'bi-newspaper',     badge: 'bg-info',      label: 'News' },
        pages:           { icon: 'bi-file-earmark',   badge: 'bg-success',   label: 'Pages' },
        products:        { icon: 'bi-box-seam',       badge: 'bg-warning',   label: 'Products' },
        orders:          { icon: 'bi-cart-check',     badge: 'bg-secondary', label: 'Orders' },
        users:           { icon: 'bi-person',         badge: 'bg-primary',   label: 'Users' },
        organizations:   { icon: 'bi-building',       badge: 'bg-dark',      label: 'Organizations', modal: true },
        'dev-requests':  { icon: 'bi-code-square',    badge: 'bg-danger',    label: 'Dev' },
        messages:        { icon: 'bi-envelope',       badge: 'bg-info',      label: 'Messages', modal: true },
        comments:        { icon: 'bi-chat-left-text', badge: 'bg-success',   label: 'Comments' },
        reviews:         { icon: 'bi-star',           badge: 'bg-warning',   label: 'Reviews' }
    };

    let timer = null;
    let xhr = null;
    let active = -1; /* keyboard navigatsiin idevhtei muriin index */

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /* Render */

    function render(q, searchHtml) {
        let html = searchHtml;
        if (!html) {
            html = q.length < 2
                ? ''
                : '<div class="search-empty"><i class="bi bi-search"></i> No results</div>';
        }
        resultsDiv.innerHTML = html;
        active = -1;

        /* Modal source-ууд: хайлтын modal-ыг бүрэн хаагаад (scroll-lock
           цэвэрлээд) дараа нь static-modal-ыг нээж view fragment-ийг ачаална.
           Хоёр modal-ыг зэрэг нээвэл/хаавал background scroll-lock алдагддаг
           тул hidden.bs.modal (once)-оор дараалуулна. */
        resultsDiv.querySelectorAll('a[data-bs-target="#static-modal"]').forEach(function (a) {
            a.addEventListener('click', function (e) {
                e.preventDefault();
                modalEl.addEventListener('hidden.bs.modal', function () {
                    const staticEl = document.getElementById('static-modal');
                    if (staticEl) {
                        bootstrap.Modal.getOrCreateInstance(staticEl).show();
                        ajaxModal(a);
                    }
                }, { once: true });
                modal.hide();
            });
        });
    }

    function searchResultsHtml(results) {
        let html = '';
        results.forEach(function (item) {
            const meta = SOURCE_META[item.source] || { icon: 'bi-file', badge: 'bg-dark', label: item.source };
            const pattern = (config.patterns && config.patterns[item.source]) || '';
            /* Route бүртгэгдээгүй модуль (|pattern -> '#') эсвэл pattern
               заагдаагүй source: хоосон линк рүү хөтөлдөг үр дүнг харуулахгүй */
            if (!pattern || pattern === '#') return;
            const href = pattern.replace('{id}', item.id);

            let subtitle = '';
            if (item.email) subtitle = item.email;
            else if (item.customer_name) subtitle = item.customer_name;
            else if (item.status) subtitle = item.status;
            else if (item.code) subtitle = item.code.toUpperCase();

            /* modal source -> static-modal дотор ачаална (шууд шилжихгүй).
               data-bs-toggle="modal"-ыг зориудаар тавихгүй: хайлтын modal
               нээлттэй байхад Bootstrap-ийн data-api static-modal-ыг зэрэг
               нээвэл scroll-lock мөргөлдөнө. Үүний оронд click handler хайлтын
               modal-ыг хаагаад дараа нь static-modal-ыг гараар нээнэ. data-bs-target нь
               ajaxModal-д аль modal руу ачаалахыг заана. */
            const modalAttrs = meta.modal
                ? ' data-bs-target="#static-modal"'
                : '';

            html += '<a class="global-search-item" href="' + href + '"' + modalAttrs + '>' +
                '<span class="search-icon"><i class="bi ' + meta.icon + '"></i></span>' +
                '<span class="search-title">' + escapeHtml(item.title || '') +
                    (subtitle ? ' <small class="text-muted">(' + escapeHtml(subtitle) + ')</small>' : '') +
                '</span>' +
                '<span class="badge ' + meta.badge + ' search-badge ms-auto">' + meta.label + '</span>' +
                '</a>';
        });
        return html;
    }

    function doSearch(q) {
        if (xhr) xhr.abort();
        if (q.length < 2 || !config.searchUrl) {
            render(q, '');
            return;
        }

        render(q, '<div class="search-loading"><span class="spinner-border spinner-border-sm"></span></div>');

        xhr = new XMLHttpRequest();
        xhr.open('GET', config.searchUrl + '?q=' + encodeURIComponent(q), true);
        xhr.onreadystatechange = function () {
            if (this.readyState !== XMLHttpRequest.DONE) return;
            try {
                const data = JSON.parse(this.responseText);
                render(q, searchResultsHtml(data.results || []));
            } catch (e) {
                render(q, '');
            }
        };
        xhr.send();
    }

    /* Keyboard navigation */

    function moveActive(step) {
        const items = resultsDiv.querySelectorAll('.global-search-item');
        if (!items.length) return;
        if (active >= 0) items[active].classList.remove('active');
        active = (active + step + items.length) % items.length;
        items[active].classList.add('active');
        items[active].scrollIntoView({ block: 'nearest' });
    }

    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            moveActive(1);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            moveActive(-1);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const items = resultsDiv.querySelectorAll('.global-search-item');
            const target = items[active >= 0 ? active : 0];
            if (target) target.click();
        }
    });

    input.addEventListener('input', function () {
        clearTimeout(timer);
        timer = setTimeout(function () {
            doSearch(input.value.trim());
        }, 250);
    });

    /* Open / close */

    trigger?.addEventListener('click', function () {
        modal.show();
    });

    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            modal.show();
        }
    });

    modalEl.addEventListener('shown.bs.modal', function () {
        input.focus();
    });

    modalEl.addEventListener('hidden.bs.modal', function () {
        input.value = '';
        render('', '');
    });

    /* Ehnii tuluv - buh commands haragdana */
    render('', '');
}

/**
 * initSidebarBadges(badgesUrl)
 * - Sidebar-ийн цэсийн зүйлс дээр тоон badge (pill) харуулах.
 *   Серверээс badge өгөгдлийг fetch-ээр авч, sidebar цэсийн холбоос бүрд
 *   таарах module-ийн badge-уудыг өнгөт pill хэлбэрээр нэмнэ.
 *
 * @param {string} badgesUrl  get хүсэлтийн URL (жишээ: /dashboard/badges).
 *                            seenUrl-ийг badgesUrl + '/seen' гэж автоматаар гаргана.
 *
 * color_map - badge өнгийг Bootstrap class руу хөрвүүлэх:
 *   green -> bg-success, blue -> bg-primary, red -> bg-danger.
 *   Тодорхойгүй өнгө -> bg-secondary (fallback).
 *
 * Badge дараалал:
 *   Модуль бүрд олон badge байж болно. green -> blue -> red дарааллаар
 *   зүүнээс баруун тийш жагсааж харуулна.
 *
 * Click handler:
 *   Хэрэглэгч badge-тэй холбоос дээр дарахад эхний badge-ийг DOM-оос
 *   устгаж, seenUrl руу post хүсэлт илгээн серверт "харсан" гэдгийг мэдэгдэнэ.
 *
 * Алдааны удирдлага:
 *   fetch болон seen post хүсэлтийн алдааг чимээгүй (silent) алгасна --
 *   badge ачаалагдахгүй байсан ч хэрэглэгчийн ажиллагаанд нөлөөлөхгүй.
 *
 * Route бүртгэгдээгүй app: |link filter нь олдоогүй route-д '#' буцаадаг.
 * badgesUrl === '#' бол badge route устгагдсан гэсэн үг тул юу ч хийхгүй
 * буцна (эс бөгөөс fetch('#') нь одоогийн хуудсыг өөрийг нь дахин татна).
 */
function initSidebarBadges(badgesUrl) {
    if (!badgesUrl || badgesUrl === '#') return;

    const seenUrl = badgesUrl + '/seen';
    const COLOR_MAP = { green: 'bg-success', blue: 'bg-primary', red: 'bg-danger', info: 'bg-info' };

    fetch(badgesUrl)
        .then(r => r.json())
        .then(data => {
            if (data.status !== 'success' || !data.badges) return;

            document.querySelectorAll('.sidebar-menu a.nav-link').forEach(function (link) {
                const href = link.getAttribute('href');
                if (!href) return;

                /* href-ийн замд badge module таарч байвал badge нэмэх */
                Object.keys(data.badges).forEach(function (module) {
                    if (href.endsWith(module) || href.endsWith(module + '/')) {
                        var items = data.badges[module];
                        /* green -> blue -> red дарааллаар badge бүрийг тусад нь харуулах */
                        var order = ['green', 'info', 'blue', 'red'];
                        order.forEach(function (color) {
                            items.forEach(function (b) {
                                if (b.color !== color) return;
                                var badge = document.createElement('span');
                                badge.className = 'badge rounded-pill ' + (COLOR_MAP[color] || 'bg-secondary');
                                badge.textContent = b.count;
                                badge.setAttribute('data-badge-module', module);
                                link.appendChild(badge);
                            });
                        });
                    }
                });
            });

            /* Click дээр seen болгох */
            document.querySelectorAll('.sidebar-menu a.nav-link').forEach(function (link) {
                link.addEventListener('click', function () {
                    const badge = link.querySelector('[data-badge-module]');
                    if (!badge || !seenUrl) return;

                    const module = badge.getAttribute('data-badge-module');
                    badge.remove();

                    csrfFetch(seenUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ module: module })
                    }).catch(function () { /* silent */ });
                });
            });
        })
        .catch(function () { /* silent - badge fetch failed */ });
}

/**
 * DOMContentLoaded:
 * - Sidebar activate
 * - static-modal reset
 * - AJAX modal binding */
document.addEventListener('DOMContentLoaded', function () {
    activateLink(window.location.pathname);

    const staticModal = document.getElementById('static-modal');
    const modalInitialContent = staticModal?.innerHTML;
    staticModal?.addEventListener('hidden.bs.modal', function () {
        this.innerHTML = modalInitialContent;
    });

    document.querySelectorAll('[data-bs-toggle="modal"][data-bs-target="#static-modal"]')
        .forEach(link => link.addEventListener('click', function (e) {
            e.preventDefault();
            ajaxModal(link);
        }));

    initScrollToTop();
    initLoggerProtocol();
    initInvalidTabFocus();
    initOrgSwitcher();
    initTopbarQuick();
    initLogoutConfirm();
});

/**
 * initLogoutConfirm()
 * - Topbar-ийн logout товчны баталгаажуулалт.
 *
 * Logout нь GET линк тул санамсаргүй click-ээс хамгаалж заавал асууна.
 * Эхлээд Bootstrap modal (#logout-confirm-modal) харуулахыг оролдоно -
 * dashboard-тай адил загвар, dark mode нийцэлтэй. Bootstrap CDN-ээс
 * ачаалагдаагүй (offline, CDN унасан) тохиолдолд native confirm()
 * fallback ашиглана - browser-ийн өөрийн dialog тул хэзээ ч ажиллана.
 * Аль ч замаар баталгаажвал data-logout-url руу шилжинэ.
 */
function initLogoutConfirm() {
    const btn = document.getElementById('topbar-logout');
    if (!btn) return;

    btn.addEventListener('click', function () {
        try {
            const modalEl = document.getElementById('logout-confirm-modal');
            if (modalEl && window.bootstrap && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                return;
            }
        } catch (err) {
            /* Bootstrap ачаалагдсан ч modal алдаа өгвөл confirm-руу унана */
        }
        if (window.confirm(btn.dataset.confirm || btn.title)) {
            window.location.href = btn.dataset.logoutUrl;
        }
    });
}

/**
 * initTopbarQuick()
 * - Topbar-ийн language / theme dropdown-уудын үйлдэл.
 *
 * Language: data-language-url attribute-тай dropdown item click хийхэд
 *   тэр GET endpoint-ийг fetch хийнэ (session-д хэлний сонголт хадгалагдана),
 *   дараа нь хуудсыг reload хийнэ.
 *
 * Theme: data-theme="light|dark" товч click хийхэд localStorage +
 *   <body data-bs-theme> attribute-ийг шууд солино (reload шаардахгүй).
 *   Идэвхтэй сонголт нь dropdown дээр active класстай харагдана.
 */
function initTopbarQuick() {
    /* Language switch */
    document.querySelectorAll('[data-language-url]').forEach(function (item) {
        item.addEventListener('click', function (e) {
            e.preventDefault();
            if (item.classList.contains('active')) return;
            fetch(item.dataset.languageUrl).finally(function () {
                window.location.reload();
            });
        });
    });

    /* Theme switch */
    const themeButtons = document.querySelectorAll('[data-theme]');
    if (!themeButtons.length) return;

    function markTheme(value) {
        themeButtons.forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.theme === value);
        });
    }

    markTheme(localStorage.getItem('data-bs-theme') === 'dark' ? 'dark' : 'light');

    themeButtons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            const value = btn.dataset.theme;
            localStorage.setItem('data-bs-theme', value);
            if (value === 'dark') {
                document.body.setAttribute('data-bs-theme', 'dark');
            } else {
                document.body.removeAttribute('data-bs-theme');
            }
            markTheme(value);
        });
    });
}

/**
 * initOrgSwitcher()
 * - Topbar дахь байгууллага сонгох dropdown-ийн хайлтын шүүлтүүр.
 *
 * Олон байгууллагатай (жишээ system_coder -> 450) үед жагсаалт хэт уртсахаар
 * тул хайлтын input-аар нэрээ шүүж, олдсон тоог шинэчилнэ. Жагсаалт нь
 * CSS-ээр scroll-той (.topbar-org-list) тул viewport-д багтана. */
function initOrgSwitcher() {
    const menu = document.getElementById('topbar-org-switcher');
    if (!menu) return;

    const input = menu.querySelector('.topbar-org-search');
    const items = Array.from(menu.querySelectorAll('.topbar-org-item'));
    const shown = menu.querySelector('.topbar-org-shown');
    const empty = menu.querySelector('.topbar-org-empty');
    if (!input) return;

    input.addEventListener('input', function () {
        const q = input.value.trim().toLowerCase();
        let count = 0;
        items.forEach(function (item) {
            const name = (item.dataset.orgName || '').toLowerCase();
            const match = q === '' || name.includes(q);
            /* Item deer Bootstrap d-flex (display:flex !important) baigaa tul
             * energiin inline none-iig important-oor tavihgui bol darahgui. */
            if (match) {
                item.style.removeProperty('display');
            } else {
                item.style.setProperty('display', 'none', 'important');
            }
            if (match) count++;
        });
        if (shown) shown.textContent = count;
        if (empty) empty.style.display = count === 0 ? '' : 'none';
    });

    /* Dropdown neegdeh burt search-iig цэвэрлэж, идэвхтэй мөр рүү scroll хийнэ. */
    menu.closest('.dropdown')?.addEventListener('shown.bs.dropdown', function () {
        input.value = '';
        input.dispatchEvent(new Event('input'));
        input.focus();
        const active = menu.querySelector('.topbar-org-item.active');
        if (active) active.scrollIntoView({ block: 'nearest' });
    });
}

/**
 * initInvalidTabFocus()
 * - Form validation fail bolohod invalid input-iin tab ruu avtomataar shiljuulne
 *
 * Bootstrap tab-content dotorh nuugdsan (inactive) tab deer
 * required input baival browser focus hiij chadahgui.
 * Ene funkts form deer 'was-validated' class nemegdehiig ajiglaad
 * ehnii invalid input-iin tab-iig idvehijuulne. */
function initInvalidTabFocus() {
    var observer = new MutationObserver(function (mutations) {
        mutations.forEach(function (m) {
            if (m.type !== 'attributes' || m.attributeName !== 'class') return;
            var form = m.target;
            if (!form.classList.contains('was-validated')) return;

            var invalid = form.querySelector(':invalid');
            if (!invalid) return;

            /* tab-pane dotorh invalid input baiwal */
            var pane = invalid.closest('.tab-pane');
            if (!pane || pane.classList.contains('active')) {
                invalid.focus();
                return;
            }

            /* Tab-iin trigger oloh */
            var paneId = pane.id;
            if (!paneId) return;
            var trigger = form.querySelector(
                '[data-bs-toggle="tab"][href="#' + paneId + '"], ' +
                '[data-bs-toggle="tab"][data-bs-target="#' + paneId + '"]'
            );
            if (trigger) {
                trigger.click();
                setTimeout(function () { invalid.focus(); }, 150);
            }
        });
    });

    document.querySelectorAll('form.needs-validation').forEach(function (form) {
        observer.observe(form, { attributes: true, attributeFilter: ['class'] });
    });
}

/**
 * initLoggerProtocol()
 * - ul.logger-protocol элементүүдийг олж, лог татаж харуулна
 *
 * HTML template-д зөвхөн data attribute тавихад хангалттай:
 *   <ul class="logger-protocol"
 *       id="logger-{table}"
 *       data-retrieve="{{ 'logs-retrieve'|link }}"
 *       data-view="{{ 'logs-view'|link }}"
 *       data-context='{"record_id":"123"}'>
 *   </ul>
 */
function initLoggerProtocol() {
    document.querySelectorAll('ul.logger-protocol:not([data-loaded])').forEach(function (logger) {
        /* Idempotent guard: initLoggerProtocol-г олон удаа дуудсан ч UL-г нэг л
         * удаа татна. Үүнгүй бол ajaxModal эсвэл бусад нөхцөлд давхар fetch хийгээд
         * UL дотор log давхардаж scroll хэт уртасах эрсдэлтэй. */
        logger.setAttribute('data-loaded', '1');

        const retrieveUrl = logger.dataset.retrieve;
        const viewUrl = logger.dataset.view;
        if (!retrieveUrl || !viewUrl) return;

        const loggerId = logger.id ?? '';
        const table = loggerId.substring(loggerId.indexOf('-') + 1);
        if (!table) return;

        const context = logger.dataset.context ? JSON.parse(logger.dataset.context) : {};

        logger.style.display = 'none';
        const spinner = logger.previousElementSibling;
        if (spinner) spinner.style.display = 'block';

        const LOG_LIMIT = 100;
        csrfFetch(`${retrieveUrl}?table=${table}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                'ORDER BY': 'id Desc',
                'CONTEXT': context,
                'LIMIT': LOG_LIMIT
            })
        })
        .then(function (res) {
            return res.text().then(function (text) {
                let data;
                try { data = JSON.parse(text); }
                catch (e) { throw new Error('HTTP ' + res.status + (res.statusText ? ' ' + res.statusText : '')); }
                if (!res.ok && !data.error) throw new Error('HTTP ' + res.status + (res.statusText ? ' ' + res.statusText : ''));
                return data;
            });
        })
        .then(function (response) {
            if (response.error) throw new Error(response.error);

            logger.innerHTML = '';
            const logModalLink = `${viewUrl}?table=${table}&id=`;
            const levelMap = {
                notice: 'light', info: 'primary', error: 'danger',
                warning: 'warning', alert: 'info', debug: 'secondary'
            };

            const logs = Object.values(response);
            logs.forEach(function (log) {
                const li = document.createElement('li');
                li.classList.add('list-group-item', 'list-group-item-action');
                li.classList.add('list-group-item-' + (levelMap[log.level] ?? 'dark'));

                const a = document.createElement('a');
                a.textContent = `${log.created_at} [${log.id}]`;
                a.href = logModalLink + log.id;
                a.setAttribute('data-bs-target', '#static-modal');
                a.setAttribute('data-bs-toggle', 'modal');
                a.addEventListener('click', function (e) {
                    e.preventDefault();
                    ajaxModal(a);
                });
                li.appendChild(a);
                li.appendChild(document.createTextNode(' '));

                const msg = document.createElement('span');
                msg.innerHTML = log.message;
                li.appendChild(msg);
                li.appendChild(document.createTextNode(' '));

                const who = document.createElement('span');
                who.classList.add('text-muted', 'small');
                const ctx = log.context ?? {};
                who.innerHTML = `<u>${ctx.action ?? ''} by ${ctx.auth_user?.username ?? ''}</u>`;
                li.appendChild(who);

                logger.appendChild(li);
            });

            /* LIMIT-д хүрсэн бол хэрэглэгчид мэдэгдэх */
            if (logs.length >= LOG_LIMIT) {
                const note = document.createElement('li');
                note.classList.add('list-group-item', 'list-group-item-secondary', 'text-center', 'small', 'fst-italic');
                note.textContent = `... (showing latest ${LOG_LIMIT} entries)`;
                logger.appendChild(note);
            }
        })
        .catch(function (error) { console.log(error.message); })
        .finally(function () {
            logger.style.display = '';
            if (spinner) spinner.style.display = 'none';
        });
    });
}
