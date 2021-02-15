const addLine = (msg, response = false) => {
    const div = document.createElement("div");
    div.classList.add("chat");
    response && div.classList.add("response");

    const p = document.createElement("p");
    p.innerHTML = msg;

    div.appendChild(p);
    document.querySelector(".chat-container").appendChild(div);

    scrollToBottom();
}

const submitHandler = event => {
    event.preventDefault();

    const command = document.querySelector("#command");
    const re = new RegExp('login\\s([\\w-\\._]+@(?:[\\w-]+\\.)+[\\w-]{2,4})\\s([\\w\\s]+)', "i");
    const match = re.exec(command.value);
    let form;

    if (match) {
        addLine(match[0].replaceAll(match[2], "".padStart(match[2].length, "*")));
        form = new FormData();
        form.set("email", match[1]);
        form.set("password", match[2]);
        form.set("_csrf_token", document.querySelector("#_csrf_token").value);
    } else {
        addLine(command.value);
        form = new FormData(document.querySelector("form"));
    }

    const ajax = window.XMLHttpRequest ? new XMLHttpRequest() : new ActiveXObject("Microsoft.XMLHTTP");
    ajax.onreadystatechange = e => {
        if (e.target.readyState == 4 && e.target.status == 200) {
            if (match) {
                const json = JSON.parse(e.target.responseText);
                addLine(json.success ? `Hello ${json.email}, how can i help you?` : "Sorry, we didn't found your email or password is wrong", true);
                return;
            }
            const aux = document.createElement("span");
            aux.innerHTML = e.target.responseText;
            document.querySelector(".chat-container").appendChild(aux.querySelector("div"));
            scrollToBottom();
        }
    };
    ajax.open("POST", match ? "login" : "processcommand", true);
    ajax.send(form);

    command.value = "";
    return false;
}

const scrollToBottom = () => {
    const messages = document.querySelector(".chat-container");
    messages.scrollTop = messages.scrollHeight;
}

document.onreadystatechange = () => {
    if (document.readyState == "complete") {
        document.querySelector("form").addEventListener("submit", submitHandler);
    }
}