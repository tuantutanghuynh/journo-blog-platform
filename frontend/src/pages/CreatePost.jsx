import { useState } from "react";
import api from "../api/axios";

export default function CreatePost() {
  // store form input values
  const [title, setTitle] = useState("");
  const [content, setContent] = useState("");
  const [excerpt, setExcerpt] = useState("");
  const [status, setStatus] = useState("draft");

  // store error and loading states
  const [error, setError] = useState(null);
  const [loading, setLoading] = useState(false);

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // call POST /api/posts with token (axios interceptor adds it automatically)
      await api.post("/posts", {
        title: title,
        content: content,
        excerpt: excerpt,
        status: status,
      });

      // redirect to home after successful create
      window.location.href = "/";

    } catch (err) {
      setError("Failed to create post. Please try again.");

    } finally {
      setLoading(false);
    }
  };

  return (
    <div>
      <h1>Create New Post</h1>

      {error && <p style={{ color: "red" }}>{error}</p>}

      <form onSubmit={handleSubmit}>
        <div>
          <label>Title</label>
          <input
            type="text"
            value={title}
            onChange={(e) => setTitle(e.target.value)}
          />
        </div>

        <div>
          <label>Excerpt (short description)</label>
          <input
            type="text"
            value={excerpt}
            onChange={(e) => setExcerpt(e.target.value)}
          />
        </div>

        <div>
          <label>Content</label>
          <textarea
            value={content}
            onChange={(e) => setContent(e.target.value)}
            rows={10}
          />
        </div>

        <div>
          <label>Status</label>
          <select value={status} onChange={(e) => setStatus(e.target.value)}>
            <option value="draft">Draft</option>
            <option value="published">Published</option>
          </select>
        </div>

        <button type="submit" disabled={loading}>
          {loading ? "Creating..." : "Create Post"}
        </button>
      </form>
    </div>
  );
}
