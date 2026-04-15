const MENU_HTML_OBJECT = document.getElementById('aside');

function Toggle()
{
    MENU_HTML_OBJECT.classList.toggle("closed");
}

document.Toggle = Toggle;