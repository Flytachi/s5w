/* Мок бэкенда: те же адреса и коды ответов, что у настоящего API.
   Файл существует, только чтобы интерфейс можно было щупать до подключения
   сервисов — когда бэкенд появится, он удаляется, а в api.js гасится MOCK.

   Занятость имён проверяется по тому, что сейчас на странице: так мок всегда
   согласован с тем, что видит человек, и не нужно держать вторую копию данных. */

(function () {
  const LATENCY = [220, 620];

  const wait = () => new Promise((r) => setTimeout(r, LATENCY[0] + Math.random() * (LATENCY[1] - LATENCY[0])));

  const uuid = () =>
    "xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx".replace(/[xy]/g, (c) => {
      const r = (Math.random() * 16) | 0;
      return (c === "x" ? r : (r & 0x3) | 0x8).toString(16);
    });

  const slug = (len = 16) => {
    const abc = "ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789-_";
    let out = "";
    for (let i = 0; i < len; i++) out += abc[(Math.random() * abc.length) | 0];
    return out;
  };

  const fail = (status, message, errors) => {
    throw new window.Api.ApiError(status, message, errors);
  };

  /** Имена, уже занятые на странице — источник для проверки уникальности. */
  const taken = (selector) =>
    Array.from(document.querySelectorAll(selector)).map((el) => el.textContent.trim().toLowerCase());

  const routes = [
    // ── бакеты ───────────────────────────────────────────────────────────────
    {
      method: "POST",
      pattern: /^\/admin\/buckets$/,
      handle: (_, body) => {
        if (!body.name) fail(422, "Validation failed", { name: ["имя обязательно"] });
        if (taken("[data-bucket-name]").includes(body.name.toLowerCase())) {
          fail(409, "Bucket name already taken", { name: ["такое имя уже занято"] });
        }

        return {
          status: 202,
          data: {
            id: uuid(),
            name: body.name,
            description: body.description || "",
            bytes: { quota: body.quotaBytes, used: 0, free: body.quotaBytes },
            status: { id: 0, name: "CREATED" },
            cache: { maxAge: null, visibility: null },
            files: 0,
            blobs: 0,
            folders: 0,
            createdAt: new Date().toISOString(),
          },
        };
      },
    },
    { method: "DELETE", pattern: /^\/admin\/buckets\/[^/]+$/, handle: () => ({ status: 202, data: null }) },
    {
      // Такого адреса у API пока нет: колонки cache_* у бакета есть, а ручки — нет.
      method: "PATCH",
      pattern: /^\/admin\/buckets\/[^/]+\/cache$/,
      handle: (_, body) => ({ status: 200, data: { maxAge: body.maxAge, visibility: body.visibility } }),
    },

    // ── папки ────────────────────────────────────────────────────────────────
    {
      method: "POST",
      pattern: /^\/admin\/buckets\/[^/]+\/folders$/,
      handle: (_, body) => {
        if (!body.name) fail(422, "Validation failed", { name: ["имя обязательно"] });
        if (!/^[\p{L}\p{N}][\p{L}\p{N} ._-]*$/u.test(body.name)) {
          fail(422, "Validation failed", { name: ["буквы, цифры, пробел, точка, дефис, подчёркивание"] });
        }
        if (taken("[data-folder-name]").includes(body.name.toLowerCase())) {
          fail(409, "Folder name already taken", { name: ["папка с таким именем уже есть"] });
        }

        return {
          status: 201,
          data: {
            name: body.name,
            public: !!body.public,
            retention: { id: Number(body.retention), name: retentionName(Number(body.retention)) },
            cache: { maxAge: null, visibility: null },
            files: 0,
            bytes: 0,
          },
        };
      },
    },
    { method: "DELETE", pattern: /^\/admin\/buckets\/[^/]+\/folders\/[^/]+$/, handle: () => ({ status: 204, data: null }) },
    {
      method: "PATCH",
      pattern: /^\/admin\/buckets\/[^/]+\/folders\/[^/]+\/cache$/,
      handle: (_, body) => ({ status: 200, data: { maxAge: body.maxAge, visibility: body.visibility } }),
    },

    // ── файлы ────────────────────────────────────────────────────────────────
    {
      method: "POST",
      pattern: /^\/admin\/buckets\/[^/]+\/files$/,
      handle: (_, body) => {
        const size = Number(body.size) || 0;
        const processed = imageResult(body, size);

        return {
          status: 201,
          data: {
            id: slug(),
            name: finalName(body.name, processed),
            folder: body.folder || null,
            public: body.public !== false,
            content: {
              size: processed.applied ? processed.result.size : size,
              mime: processed.applied ? processed.result.mime : body.mime || "application/octet-stream",
              extension: (body.name.split(".").pop() || "").toLowerCase(),
              hash: slug(14).toLowerCase(),
            },
            processed,
            deduplicated: Math.random() < 0.18,
            expiresAt: null,
            createdAt: new Date().toISOString(),
          },
        };
      },
    },
    { method: "DELETE", pattern: /^\/admin\/buckets\/[^/]+\/files\/[^/]+$/, handle: () => ({ status: 204, data: null }) },

    // ── ссылки ───────────────────────────────────────────────────────────────
    {
      method: "POST",
      pattern: /^\/admin\/buckets\/[^/]+\/files\/[^/]+\/link$/,
      handle: (path, body) => {
        const stateful = body.revocable || body.maxDownloads;
        const ttl = Number(body.ttl) || 3600;

        return {
          status: 201,
          data: {
            id: stateful ? Math.floor(Math.random() * 900 + 100) : null,
            url: location.origin + "/t/" + btoa(slug(38)).replace(/[+/=]/g, "").slice(0, 56),
            expiresAt: new Date(Date.now() + ttl * 1000).toISOString(),
            revocable: !!body.revocable,
            maxDownloads: body.maxDownloads ? Number(body.maxDownloads) : null,
            downloads: 0,
            revoked: false,
            disposition: { id: Number(body.disposition), name: Number(body.disposition) === 1 ? "ATTACHMENT" : "INLINE" },
            note: body.note || "",
            slug: path.split("/files/")[1].split("/")[0],
          },
        };
      },
    },
    { method: "DELETE", pattern: /^\/admin\/buckets\/[^/]+\/links\/\d+$/, handle: () => ({ status: 204, data: null }) },
    {
      method: "DELETE",
      pattern: /^\/admin\/buckets\/[^/]+\/links$/,
      handle: () => ({ status: 200, data: { epoch: Number(document.querySelector('[data-counter="epoch"]')?.textContent || 3) + 1 } }),
    },

    // ── токены ───────────────────────────────────────────────────────────────
    {
      method: "POST",
      pattern: /^\/admin\/buckets\/[^/]+\/tokens$/,
      handle: (_, body) => {
        if (!body.name) fail(422, "Validation failed", { name: ["название обязательно"] });

        const days = body.expiresInDays ? Number(body.expiresInDays) : null;

        return {
          status: 201,
          data: {
            token: "s5w_" + slug(32).replace(/[-_]/g, "a").toLowerCase(),
            accessToken: {
              id: Math.floor(Math.random() * 900 + 100),
              name: body.name,
              status: { id: 1, name: "ACTIVE" },
              expired: false,
              expiresAt: days ? new Date(Date.now() + days * 86400000).toISOString() : null,
              lastUsedAt: null,
              createdAt: new Date().toISOString(),
            },
          },
        };
      },
    },
    {
      method: "POST",
      pattern: /^\/admin\/buckets\/[^/]+\/tokens\/\d+\/rotate$/,
      handle: () => ({ status: 200, data: { token: "s5w_" + slug(32).replace(/[-_]/g, "a").toLowerCase() } }),
    },
    {
      method: "PATCH",
      pattern: /^\/admin\/buckets\/[^/]+\/tokens\/\d+\/status$/,
      handle: (_, body) => ({
        status: 200,
        data: { status: { id: Number(body.status), name: Number(body.status) === 1 ? "ACTIVE" : "INACTIVE" } },
      }),
    },
    { method: "DELETE", pattern: /^\/admin\/buckets\/[^/]+\/tokens\/\d+$/, handle: () => ({ status: 204, data: null }) },
  ];

  function retentionName(id) {
    return ["NONE", "DAY", "WEEK", "MONTH", "QUARTER", "HALF_YEAR", "YEAR"][id] || "NONE";
  }

  /** Повторяет решения ImageProcessor: что произойдёт с картинкой. */
  function imageResult(body, size) {
    const isImage = (body.mime || "").startsWith("image/");
    const format = body.format || "ORIGINAL";
    const quality = body.quality ? Number(body.quality) : null;
    const maxWidth = body.maxWidth ? Number(body.maxWidth) : null;
    const maxHeight = body.maxHeight ? Number(body.maxHeight) : null;
    const asked = format !== "ORIGINAL" || quality || maxWidth || maxHeight;

    if (!asked) return { applied: false, reason: "no options" };
    if (!isImage) fail(422, "Image processing is not applicable to " + (body.mime || "этого файла"));
    if ((body.mime || "").includes("gif")) return { applied: false, reason: "animated" };

    const width = 3000;
    const height = 2000;
    const scale = maxWidth || maxHeight ? Math.min((maxWidth || 1e9) / width, (maxHeight || 1e9) / height, 1) : 1;
    const outWidth = Math.round(width * scale);
    const outHeight = Math.round(height * scale);
    const mime = format === "ORIGINAL" ? body.mime : "image/" + format.toLowerCase();
    const ratio = scale * scale * ((quality || 82) / 100) * (format === "WEBP" ? 0.45 : format === "AVIF" ? 0.3 : 0.8);
    const outSize = Math.max(1024, Math.round(size * ratio));

    const operations = [];
    if (scale < 1) operations.push(`resize:${outWidth}x${outHeight}`);
    operations.push(`encode:${mime.split("/")[1]}@${quality || 82}`);

    if (outSize >= size && scale === 1 && mime === body.mime) {
      return { applied: false, reason: "output is larger than source" };
    }

    return {
      applied: true,
      operations,
      source: { width, height, size, mime: body.mime },
      result: { width: outWidth, height: outHeight, size: outSize, mime },
    };
  }

  /** Расширение идёт за содержимым — как finalName() на сервере. */
  function finalName(name, processed) {
    if (!processed.applied || processed.source.mime === processed.result.mime) return name;

    const ext = processed.result.mime.split("/")[1];
    const dot = name.lastIndexOf(".");
    return (dot > 0 ? name.slice(0, dot) : name) + "." + ext;
  }

  async function handle(method, path, body) {
    await wait();

    const route = routes.find((r) => r.method === method && r.pattern.test(path));
    if (!route) {
      throw new window.Api.ApiError(404, "Мок не знает такого адреса: " + method + " " + path);
    }

    return route.handle(path, body || {});
  }

  window.MockApi = { handle };
})();
