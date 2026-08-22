import type { ComponentProps } from 'react';
import Verify from './verify';

export default function BulkVerify(props: ComponentProps<typeof Verify>) {
    return <Verify {...props} />;
}
