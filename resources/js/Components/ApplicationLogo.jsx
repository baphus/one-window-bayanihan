export default function ApplicationLogo(props) {
    const { className, ...rest } = props;
    return (
        <img
            src="/logo.png"
            alt="One Window Bayanihan"
            className={className}
            {...rest}
        />
    );
}
