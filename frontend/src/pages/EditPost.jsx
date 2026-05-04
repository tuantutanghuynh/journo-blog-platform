import { useState, useEffect } from "react";
import { useParams } from "react-router-dom";
import api from "../api/axios";

export default function EditPost() {
  // get post ID from URL — e.g. /posts/1/edit → id = "1"
  const { id } = useParams();

  // store form input values
  const [title, setTitle] = useState("");
  const [content, setContent] = useState("");
  const [excerpt, setExcerpt] = useState("");
  const [status, setStatus] = useState("draft");

  // store loading and error states
  const [loading, setLoading] = useState(false);
  const [fetching, setFetching] = useState(true);
  const [error, setError] = useState(null);

  // fetch existing post data when page loads
  useEffect(() => {
    fetchPost();
  }, []);

  const fetchPost = async () => {
    try {
      // get current post data to pre-fill the form
      const res = await api.get(`/posts/${id}`);

      // pre-fill form with existing values
      setTitle(res.data.title);
      setContent(res.data.content);
      setExcerpt(res.data.excerpt ?? "");
      setStatus(res.data.status);

    } catch (err) {
      setError("Failed to load post");

    } finally {
      setFetching(false);
    }
  };

  const handleSubmit = async (event) => {
    event.preventDefault();
    setLoading(true);
    setError(null);

    try {
      // call PUT /api/posts/{id} with updated data
      await api.put(`/posts/${id}`, {
        title: title,
        content: content,
        excerpt: excerpt,
        status: status,
      });

      // redirect to post detail after successful update
      window.location.href = `/posts/${id}`;

    } catch (err) {
      setError("Failed to update post. You may not have permission.");

    } finally {
      setLoading(false);
    }
  };

  // show loading while fetching post data
  if (fetching) return <p>Loading...</p>;
  if (error) return <p style={{ color: "red" }}>{error}</p>;

  return (
    <div>
      <h1>Edit Post</h1>

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
          <label>Excerpt</label>
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
          {loading ? "Saving..." : "Save Changes"}
        </button>
      </form>
    </div>
  );
}
