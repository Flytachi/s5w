/* Единственная точка обращения к серверу.
   Пока за ней мок (mock-api.js) — когда появится бэкенд, здесь останется
   только fetch, а mock-api.js удаляется целиком. Ни один вызывающий код
   об этом не узнает: сигнатура и формат ошибок одинаковые. */

(function () {
  const MOCK = true;

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
    if (MOCK) return window.MockApi.handle(method, path, body);

    const init = { method, headers: { Accept: "application/json" } };
    if (body instanceof FormData) {
      init.body = body;
    } else if (body !== undefined) {
      init.headers["Content-Type"] = "application/json";
      init.body = JSON.stringify(body);
    }

    const res = await fetch(path, init);
    const text = await res.text();
    const json = text ? JSON.parse(text) : null;

    if (!res.ok) {
      throw new ApiError(res.status, (json && json.message) || "Запрос не прошёл", json && json.errors);
    }
    return { status: res.status, data: json };
  }

  window.Api = {
    ApiError,
    isMock: () => MOCK,
    get: (path) => request("GET", path),
    post: (path, body) => request("POST", path, body),
    put: (path, body) => request("PUT", path, body),
    patch: (path, body) => request("PATCH", path, body),
    delete: (path) => request("DELETE", path),
  };
})();
