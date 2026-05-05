import { useState, useEffect } from "react";
import { useParams } from "react-router-dom";
import api from "../api/axios";

export default function PostDetail() {
  //get post ID from URL -e.g/posts/1 -> id ="1"
  const { id } = useParams();

  //store the post datta
  const [post, setPost] = useState(null);

  //store comment
  const [comments, setComments] = useState([]);

  //store loading and error
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState(null);

  //store delete loading state
  const [deleting, setDeleting] = useState(false);

  //store new comment input
  const [newComment, setNewComment] = useState("");
  const [commenting, setCommenting] = useState(false);

  //fetch post and comments when page load
  useEffect(() => {
    fetchPost();
    fetchComments();
  }, []);

  const fetchPost = async () => {
    try {
      const res = await api.get(`/posts/${id}`);
      setPost(res.data);
    } catch (err) {
      setError("Post not foun");
    } finally {
      setLoading(false);
    }
  };

  const fetchComments = async () => {
    try {
      const res = await api.get(`/posts/${id}/comments`);
      setComments(res.data);
    } catch (err) {
      //silently fail - comment not critical
    }
  };

  const handleDelete = async () => {
    //ask user to confirm before deleting
    const confirmed = window.confirm(
      "Are you sure you want to delete this post",
    );
    if (!confirmed) return;
    setDeleting(true);

    try {
      //call Delete /api/posts/{id}
      await api.delete(`/posts/${id}`);

      //redirect to home after successfull delete
      window.location.href = "/";
    } catch (err) {
      alert("Failed to delete post. You may not have permisssion");
      setDeleting(false);
    }
  };

  const handleCommentSubmit = async (event) => {
    event.preventDefault();
    setCommenting(true);

    try {
      //call POST /api/posts/{id}/comments
      await api.post(`/posts/${id}/comments`, {
        content: newComment,
      });

      //clear input after successfull submit
      setNewComment("");

      //refresh comments list
      fetchComments();
    } catch (err) {
      alert("Failed to post comment. Please login first.");
    } finally {
      setCommenting(false);
    }
  };

  if (loading) {
    return <p>Loading....</p>;
  }
  if (error) {
    return <p style={{ color: "red" }}>{error}</p>;
  }

  return (
    <div>
      <h1>{post.title}</h1>
      <p>By: {post.author?.name}</p>
      <p>Category: {post.category?.name}</p>
      <a href={`/posts/${id}/edit`}>Edit Post</a>
      <button onClick={handleDelete} disabled={deleting}>
        {deleting ? "Deleting..." : "Delete post"}
      </button>
      <hr />

      <p>{post.content}</p>
      <hr />
      <h2>Comments ({comments.length})</h2>
      {localStorage.getItem("token") && (
        <form onSubmit={handleCommentSubmit}>
          <div>
            <textarea
              value={newComment}
              onChange={(e) => setNewComment(e.target.value)}
              placeholder="Write a comment..."
              rows={3}
            />
          </div>
          <button type="submit" disabled={commenting}>
            {commenting ? "Posting..." : "Post Comment"}
          </button>
        </form>
      )}
      {comments.length === 0 ? (
        <p>No comments yet</p>
      ) : (
        comments.map((comment) => (
          <div key={comment.id}>
            <p>
              <strong>{comment.user?.name}</strong>: {comment.content}
            </p>
          </div>
        ))
      )}
    </div>
  );
}
