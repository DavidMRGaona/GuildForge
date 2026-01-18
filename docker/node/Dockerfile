FROM node:22-alpine

# Install system dependencies
RUN apk add --no-cache git

# Create application directory
WORKDIR /var/www/html

# Use the existing node user (uid/gid 1000 already exists in node image)
USER node

EXPOSE 5173

CMD ["npm", "run", "dev"]
