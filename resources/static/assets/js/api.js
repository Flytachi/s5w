/* Единственная точка обращения к серверу.
   Ошибки приходят в одном виде: статус, сообщение и разбор по полям формы,
   если сервер его прислал. */

(function () {

  class ApiError extends Error {
    constructor(status, message, errors) {
      super(message);
      this.status = status;
      this.errors = errors || null;
    }

    /** Ошибки полей формы: { "name": ["уже занято"] } */
    field(name) {
      return this.errors && this.errors[name] ? this.errors[name][0] : null;
    }
  }

  async function request(method, path, body) {
    const init = { method, headers: { Accept: "application/json" } };
    if (body instanceof FormData) {
      init.body = body;
    } else if (body !== undefined) {
      init.headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(body);
    }

    const res = await fetch(path, init);
    const text = await res.text();
    let json = null;
    try {
      json = text ? JSON.parse(text) : null;
    } catch (e) {
      // сервер ответил не JSON — покажем то, что есть
      json = { message: text.slice(0, 200) };
    }

    if (res.status === 401 && !document.querySelector("[data-login-form]")) {
      location.href = "/admin/ui/login?next=" + encodeURIComponent(location.pathname + location.search);
      await new Promise(() => {});
    }

    if (!res.ok) {
      throw new ApiError(res.status, (json && json.message) || "Не удалось выполнить запрос", json && json.errors);
    }
    return { status: res.status, data: json };
  }

  window.Api = {
    ApiError,
    get: (path) => request("GET", path),
    post: (path, body) => request("POST", path, body),
    put: (path, body) => request("PUT", path, body),
    patch: (path, body) => request("PATCH", path, body),
    delete: (path) => request("DELETE", path),
  };
})();
