export default function Loading({ text = 'Loading...' }) {
  return (
    <div className="loading">
      <div className="tmc-dots" role="status" aria-label={text}>
        <i /><i /><i />
      </div>
      {text && <span>{text}</span>}
    </div>
  );
}
